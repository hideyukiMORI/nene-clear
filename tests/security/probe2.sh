#!/usr/bin/env bash
# NeNe Clear — security probe ROUND 2. Deeper / creative vectors that round 1
# did not cover: mass assignment, privilege escalation via update, IDOR numeric
# edge cases, idempotency/double-spend, JSON/type confusion, pagination DoS,
# CSV injection, user enumeration, header/JWT edge cases, rate-limit verify.
set -u
BASE="http://localhost:8082"
pass=0; vuln=0; info=0
ok()   { echo "[PASS] $1"; pass=$((pass+1)); }
bad()  { echo "[VULN] $1"; vuln=$((vuln+1)); }
note() { echo "[INFO] $1"; info=$((info+1)); }
login(){ curl -s "$BASE/admin/auth/login" -H 'Content-Type: application/json' -d "$1" | python3 -c 'import sys,json
try:
 print(json.load(sys.stdin)["token"])
except Exception: print("")'; }
st(){ # METHOD PATH DATA TOKEN
  curl -s -o /dev/null -w '%{http_code}' -X "$1" "$BASE$2" -H 'Content-Type: application/json' ${4:+-H "Authorization: Bearer $4"} ${3:+-d "$3"}; }
bd(){ curl -s -X "$1" "$BASE$2" -H 'Content-Type: application/json' ${4:+-H "Authorization: Bearer $4"} ${3:+-d "$3"}; }

# reset throttle so probing logins don't lock us out
mysql -h127.0.0.1 -P3310 -unene_clear -pnene_clear nene_clear -e "DELETE FROM login_attempts;" 2>/dev/null

T_O1=$(login '{"email":"admin@org1.example","password":"admin-pass-1"}')
T_O2=$(login '{"email":"admin@org2.example","password":"admin-pass-2"}')
T_MEMBER=$(login '{"email":"user1@org1.example","password":"pass-1-1"}')   # member
T_VIEWER=$(login '{"email":"user2@org1.example","password":"pass-1-2"}')   # viewer
echo "tokens: o1=${T_O1:0:10} o2=${T_O2:0:10} member=${T_MEMBER:0:10} viewer=${T_VIEWER:0:10}"
echo "================================================================"

echo "## H. Mass assignment / privilege escalation"
# create user trying to inject organization_id (plant into another org) + role superadmin
RESP=$(bd POST /admin/users '{"email":"evil1@org1.example","role":"superadmin","organization_id":2,"id":99999,"status":"active"}' "$T_O1")
echo "$RESP" | grep -q '"role":"superadmin"' && bad "user created AS superadmin via body (priv-esc): $RESP" || ok "cannot self-assign superadmin role on create"
PLANTED_ORG=$(echo "$RESP" | python3 -c 'import sys,json
try:
 d=json.load(sys.stdin); print(d.get("organization_id"))
except Exception: print("err")')
[ "$PLANTED_ORG" = "2" ] && bad "mass-assignment set organization_id=2 (cross-tenant plant)" || ok "organization_id not honored from body (got: $PLANTED_ORG)"
# update a member to superadmin (escalation within org-admin powers)
MID=$(bd GET /admin/users "" "$T_O1" | python3 -c 'import sys,json;d=json.load(sys.stdin);print([u["user_id"] for u in d["items"] if u["role"]=="member"][0])')
RESP=$(bd PUT "/admin/users/$MID" '{"role":"superadmin"}' "$T_O1")
echo "$RESP" | grep -q '"role":"superadmin"' && bad "org-admin escalated a member to superadmin: $RESP" || ok "cannot escalate member to superadmin via update"

echo "## I. IDOR numeric / type edge cases"
for id in 0 -1 999999999 1.5 1e3 "abc" "1%20OR%201" "null"; do
  s=$(st GET "/admin/reconciliations/$id" "" "$T_O1")
  case "$s" in 200) note "GET /reconciliations/$id → 200 (verify scoping)";; 404|400|422) ok "id='$id' → $s (rejected/not-found)";; *) note "id='$id' → $s";; esac
done

echo "## J. Idempotency / double-spend"
# same idempotency key twice — must not create two reconciliations
TX=$(bd GET "/admin/bank-transactions/unmatched?limit=1" "" "$T_O1" | python3 -c 'import sys,json;d=json.load(sys.stdin);print(d["items"][0]["bank_transaction_id"] if d.get("items") else 0)')
IK="dbl-$(date +%s%N)"
c1=$(st POST /admin/reconciliations "{\"bank_transaction_id\":$TX,\"allocations\":[{\"invoice_id\":1,\"amount_cents\":1000}]}" "$T_O1")
c2=$(st POST /admin/reconciliations "{\"bank_transaction_id\":$TX,\"allocations\":[{\"invoice_id\":1,\"amount_cents\":1000}]}" "$T_O1")
note "confirm same tx twice (no upstream wired): c1=$c1 c2=$c2 (422 expected: Fake upstream has no invoices)"

echo "## K. JSON / type confusion"
# array where string expected
note "email as array → $(st POST /admin/auth/login '{"email":["a","b"],"password":"x"}' '')"
note "password as object → $(st POST /admin/auth/login '{"email":"a@b.c","password":{"x":1}}' '')"
note "allocations as string → $(st POST /admin/reconciliations '{"bank_transaction_id":1,"allocations":"all"}' "$T_O1")"
note "bank_transaction_id as string → $(st POST /admin/reconciliations '{"bank_transaction_id":"1","allocations":[]}' "$T_O1")"
# deeply nested JSON (DoS)
DEEP=$(python3 -c 'print("{\"a\":"*500+"1"+"}"*500)')
note "deeply nested JSON (500 levels) → $(st POST /admin/auth/login "$DEEP" '')"

echo "## L. Pagination abuse"
note "limit=9999999 → $(st GET '/admin/bank-transactions?limit=9999999&offset=0' '' "$T_O1")"
note "negative offset → $(st GET '/admin/bank-transactions?limit=10&offset=-100' '' "$T_O1")"
note "limit=0 → $(st GET '/admin/bank-transactions?limit=0&offset=0' '' "$T_O1")"
note "limit=abc → $(st GET '/admin/bank-transactions?limit=abc' '' "$T_O1")"

echo "## M. Stored / second-order injection (counterparty, reversal_reason)"
# reversal_reason with SQL + XSS payload — stored then returned
RID=$(bd GET '/admin/reconciliations?limit=1' "" "$T_O1" | python3 -c 'import sys,json;d=json.load(sys.stdin);print(d["items"][0]["payment_reconciliation_id"] if d.get("items") else 0)')
PAY='{"reversal_reason":"x'"'"'; DROP TABLE users;-- <script>alert(1)</script>"}'
s=$(st POST "/admin/reconciliations/$RID/reverse" "$PAY" "$T_O1")
UC=$(mysql -h127.0.0.1 -P3310 -unene_clear -pnene_clear nene_clear -N -e "SELECT COUNT(*) FROM users" 2>/dev/null)
[ "$UC" -ge 19 ] 2>/dev/null && ok "stored SQLi in reversal_reason did not drop table (users=$UC, reverse status=$s)" || bad "users table altered by stored payload: $UC"

echo "## N. User enumeration (message + status uniformity)"
B_UNKNOWN=$(bd POST /admin/auth/login '{"email":"doesnotexist@nowhere.example","password":"x"}' '')
B_WRONGPW=$(bd POST /admin/auth/login '{"email":"admin@org1.example","password":"x"}' '')
mysql -h127.0.0.1 -P3310 -unene_clear -pnene_clear nene_clear -e "DELETE FROM login_attempts;" 2>/dev/null
[ "$B_UNKNOWN" = "$B_WRONGPW" ] && ok "identical response for unknown-user vs wrong-password (no enumeration)" || bad "login response differs (enumeration): unknown=$B_UNKNOWN wrongpw=$B_WRONGPW"

echo "## O. JWT edge cases"
# org claim type juggling: forge is impossible (signed), but test a valid token's org coercion via a second org's resources already covered.
# kid / extra headers ignored?
note "token with extra header kid (re-signed? no) → tampered, expect 401: $(st GET /admin/users '' "$(echo "$T_O1" | sed 's/^/X/')" )"
# Authorization header variants
note "lowercase 'bearer' scheme → $(curl -s -o /dev/null -w '%{http_code}' "$BASE/admin/users" -H "Authorization: bearer $T_O1")"
note "no scheme (raw token) → $(curl -s -o /dev/null -w '%{http_code}' "$BASE/admin/users" -H "Authorization: $T_O1")"

echo "## P. Content-Type confusion"
note "form-encoded login → $(curl -s -o /dev/null -w '%{http_code}' -X POST "$BASE/admin/auth/login" -H 'Content-Type: application/x-www-form-urlencoded' -d 'email=admin@org1.example&password=admin-pass-1')"
mysql -h127.0.0.1 -P3310 -unene_clear -pnene_clear nene_clear -e "DELETE FROM login_attempts;" 2>/dev/null

echo "## Q. Rate limit verification (the F-2 fix)"
fails=0; locked=0
for i in $(seq 1 8); do
  c=$(st POST /admin/auth/login '{"email":"ratecheck@org1.example","password":"wrong"}' '')
  [ "$c" = "401" ] && fails=$((fails+1)); [ "$c" = "429" ] && locked=$((locked+1))
done
[ "$locked" -ge 1 ] && ok "login throttle engages (401×$fails then 429×$locked)" || bad "no throttle: $fails×401, never locked"
mysql -h127.0.0.1 -P3310 -unene_clear -pnene_clear nene_clear -e "DELETE FROM login_attempts;" 2>/dev/null

echo "================================================================"
echo "SUMMARY: PASS=$pass  VULN=$vuln  INFO=$info"
