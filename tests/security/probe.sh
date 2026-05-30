#!/usr/bin/env bash
# NeNe Clear — security probe. Authorized assessment against the local
# MySQL-backed instance on :8082 with seeded multi-tenant data.
#
# Each check prints: [PASS] expected-safe behaviour / [VULN] a real finding /
# [INFO] observation. No external targets; localhost only.
set -u
BASE="http://localhost:8082"
JWT_SECRET="nene-clear-dev-secret-change-in-production"
pass=0; vuln=0; info=0
ok()   { echo "[PASS] $1"; pass=$((pass+1)); }
bad()  { echo "[VULN] $1"; vuln=$((vuln+1)); }
note() { echo "[INFO] $1"; info=$((info+1)); }

code() { # METHOD PATH [DATA] [HEADER] -> http status
  local m="$1" p="$2" d="${3:-}" h="${4:-}"
  if [ -n "$d" ]; then
    curl -s -o /dev/null -w '%{http_code}' -X "$m" "$BASE$p" -H 'Content-Type: application/json' ${h:+-H "$h"} -d "$d"
  else
    curl -s -o /dev/null -w '%{http_code}' -X "$m" "$BASE$p" ${h:+-H "$h"}
  fi
}
body() {
  local m="$1" p="$2" d="${3:-}" h="${4:-}"
  if [ -n "$d" ]; then
    curl -s -X "$m" "$BASE$p" -H 'Content-Type: application/json' ${h:+-H "$h"} -d "$d"
  else
    curl -s -X "$m" "$BASE$p" ${h:+-H "$h"}
  fi
}

# Tokens
TOK_O1=$(body POST /admin/auth/login '{"email":"admin@org1.example","password":"admin-pass-1"}' | python3 -c 'import sys,json;print(json.load(sys.stdin)["token"])')
TOK_O2=$(body POST /admin/auth/login '{"email":"admin@org2.example","password":"admin-pass-2"}' | python3 -c 'import sys,json;print(json.load(sys.stdin)["token"])')
TOK_VIEWER=$(body POST /admin/auth/login '{"email":"user2@org1.example","password":"pass-1-2"}' | python3 -c 'import sys,json;print(json.load(sys.stdin)["token"])')
TOK_SUPER=$(body POST /admin/auth/login '{"email":"root@nene-clear.dev","password":"root-pass-1234"}' | python3 -c 'import sys,json;print(json.load(sys.stdin)["token"])')
echo "tokens: o1=${TOK_O1:0:12}.. o2=${TOK_O2:0:12}.. viewer=${TOK_VIEWER:0:12}.. super=${TOK_SUPER:0:12}.."
echo "================================================================"

echo "## A. Authentication"
[ "$(code GET /admin/users '' '')" = "401" ] && ok "no token → 401 on protected route" || bad "missing token not rejected"
[ "$(code GET /admin/users '' 'Authorization: Bearer garbage')" = "401" ] && ok "garbage token → 401" || bad "garbage token accepted"
# alg:none forgery
NONE=$(python3 - <<PY
import base64,json
def b(d): return base64.urlsafe_b64encode(json.dumps(d,separators=(',',':')).encode()).rstrip(b'=').decode()
print(b({"alg":"none","typ":"JWT"})+'.'+b({"sub":1,"org":None,"role":"superadmin"})+'.')
PY
)
[ "$(code GET /admin/users '' "Authorization: Bearer $NONE")" = "401" ] && ok "alg:none forgery → 401" || bad "alg:none forgery ACCEPTED"
# signature stripped / tampered role
HDR=$(echo "$TOK_O1" | cut -d. -f1); PL=$(echo "$TOK_O1" | cut -d. -f2)
FORGED="$HDR.$PL.AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA"
[ "$(code GET /admin/users '' "Authorization: Bearer $FORGED")" = "401" ] && ok "bad signature → 401" || bad "bad signature accepted"
# tampered payload (escalate viewer→superadmin) keeping old signature
TPL=$(python3 -c 'import base64,json;print(base64.urlsafe_b64encode(json.dumps({"sub":3,"org":None,"role":"superadmin"}).encode()).rstrip(b"=").decode())')
VHDR=$(echo "$TOK_VIEWER"|cut -d. -f1); VSIG=$(echo "$TOK_VIEWER"|cut -d. -f3)
[ "$(code GET /admin/organizations '' "Authorization: Bearer $VHDR.$TPL.$VSIG")" != "200" ] && ok "payload tamper (role escalation) rejected" || bad "payload tamper ACCEPTED → privilege escalation"

echo "## B. Multi-tenant isolation (IDOR)"
# org2 user listing must not contain org1 emails
O2_USERS=$(body GET /admin/users '' "Authorization: Bearer $TOK_O2")
echo "$O2_USERS" | grep -q "org1.example" && bad "org2 token sees org1 users (cross-tenant leak)" || ok "user list scoped to caller org"
# direct fetch of an org1 reconciliation id using org2 token
R_O1=$(body GET '/admin/reconciliations?limit=1' '' "Authorization: Bearer $TOK_O1" | python3 -c 'import sys,json;d=json.load(sys.stdin);print(d["items"][0]["payment_reconciliation_id"] if d.get("items") else 0)')
ST=$(code GET "/admin/reconciliations/$R_O1" '' "Authorization: Bearer $TOK_O2")
[ "$ST" = "404" ] && ok "cross-tenant reconciliation fetch → 404 (id=$R_O1)" || bad "cross-tenant reconciliation readable (status=$ST)"
# cross-tenant reverse (write)
ST=$(code POST "/admin/reconciliations/$R_O1/reverse" '{"reversal_reason":"attack"}' "Authorization: Bearer $TOK_O2")
[ "$ST" = "404" ] || [ "$ST" = "422" ] && ok "cross-tenant reverse blocked (status=$ST)" || bad "cross-tenant reverse allowed (status=$ST)"
# cross-tenant user delete
U_O1=$(echo "$O2_USERS" >/dev/null; body GET /admin/users '' "Authorization: Bearer $TOK_O1" | python3 -c 'import sys,json;d=json.load(sys.stdin);print([u["user_id"] for u in d["items"] if u["role"]=="member"][0])')
ST=$(code DELETE "/admin/users/$U_O1" '' "Authorization: Bearer $TOK_O2")
[ "$ST" = "404" ] && ok "cross-tenant user delete → 404 (id=$U_O1)" || bad "cross-tenant user delete status=$ST"

echo "## C. RBAC / capability"
# viewer cannot manage users (write)
ST=$(code POST /admin/users '{"email":"x@org1.example","role":"admin"}' "Authorization: Bearer $TOK_VIEWER")
[ "$ST" = "403" ] && ok "viewer POST /users → 403" || bad "viewer can create users (status=$ST)"
# viewer cannot manage organizations
ST=$(code GET /admin/organizations '' "Authorization: Bearer $TOK_VIEWER")
[ "$ST" = "403" ] && ok "viewer GET /organizations → 403" || bad "viewer reads organizations (status=$ST)"
# admin (org-scoped) cannot manage organizations (superadmin-only)
ST=$(code POST /admin/organizations '{"slug":"evil","name":"Evil"}' "Authorization: Bearer $TOK_O1")
[ "$ST" = "403" ] && ok "org-admin POST /organizations → 403" || bad "org-admin created organization (status=$ST)"

echo "## D. SQL injection"
for inj in "' OR '1'='1" "'; DROP TABLE users;--" "1 UNION SELECT password_hash FROM users" "%27%20OR%201=1--"; do
  ST=$(code GET "/admin/bank-transactions?counterparty=$(python3 -c "import urllib.parse,sys;print(urllib.parse.quote(sys.argv[1]))" "$inj")" '' "Authorization: Bearer $TOK_O1")
  [ "$ST" = "200" ] || [ "$ST" = "400" ] || [ "$ST" = "422" ] && ok "SQLi filter payload handled (status=$ST): ${inj:0:20}" || bad "SQLi suspicious status=$ST: $inj"
done
# login SQLi — 401 (auth fail) or 400/422 (input rejected) are both safe
ST=$(code POST /admin/auth/login "{\"email\":\"admin@org1.example'\''--\",\"password\":\"x\"}" '')
case "$ST" in 400|401|422) ok "login SQLi safely rejected ($ST)";; *) bad "login SQLi status=$ST";; esac
USERS_OK=$(mysql -h127.0.0.1 -P3310 -unene_clear -pnene_clear nene_clear -N -e "SELECT COUNT(*) FROM users" 2>/dev/null)
[ "$USERS_OK" -ge 19 ] 2>/dev/null && ok "users table intact after SQLi attempts ($USERS_OK rows)" || bad "users table row count anomalous: $USERS_OK"

echo "## E. Financial integrity (over-allocation / negative / overflow)"
# negative allocation
ST=$(code POST /admin/reconciliations/confirm '{"bank_transaction_id":1,"allocations":[{"invoice_id":1,"amount_cents":-100000}]}' "Authorization: Bearer $TOK_O1")
note "negative allocation amount → status=$ST"
# huge overflow amount
ST=$(code POST /admin/reconciliations/confirm '{"bank_transaction_id":1,"allocations":[{"invoice_id":1,"amount_cents":99999999999999999999}]}' "Authorization: Bearer $TOK_O1")
note "overflow allocation amount → status=$ST"

echo "## F. Input / method / disclosure"
# oversized body
BIG=$(python3 -c 'print("{\"email\":\""+"a"*200000+"@x.com\",\"password\":\"x\"}")')
ST=$(code POST /admin/auth/login "$BIG" '')
note "200KB login body → status=$ST"
# malformed JSON
ST=$(code POST /admin/auth/login '{"email":' '')
[ "$ST" -ge 400 ] 2>/dev/null && ok "malformed JSON → $ST" || bad "malformed JSON status=$ST"
# method tampering
ST=$(code PUT /admin/users '' "Authorization: Bearer $TOK_O1")
note "PUT /admin/users (unsupported) → status=$ST"
# error disclosure: does a 500/exception leak stack traces?
ERRBODY=$(body GET '/admin/reconciliations/99999999' '' "Authorization: Bearer $TOK_O1")
echo "$ERRBODY" | grep -qiE "stack trace|/home/xi|vendor/|PDOException|SQLSTATE" && bad "error response leaks internals: $(echo "$ERRBODY"|head -c 80)" || ok "not-found error is clean (no stack/paths)"
# path traversal on an id route
ST=$(code GET '/admin/reconciliations/..%2f..%2fetc%2fpasswd' '' "Authorization: Bearer $TOK_O1")
note "path traversal in id segment → status=$ST"

echo "## G. Brute force / rate limiting"
fails=0
for i in $(seq 1 12); do
  c=$(code POST /admin/auth/login '{"email":"admin@org1.example","password":"wrong"}' '')
  [ "$c" = "401" ] && fails=$((fails+1))
done
note "12 rapid wrong-password logins: $fails×401 (rate limiting: $([ $fails -ge 12 ] && echo 'ABSENT — no lockout/throttle' || echo present))"

echo "================================================================"
echo "SUMMARY: PASS=$pass  VULN=$vuln  INFO=$info"
