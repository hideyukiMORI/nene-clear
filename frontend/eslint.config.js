import js from '@eslint/js'
import globals from 'globals'
import reactHooks from 'eslint-plugin-react-hooks'
import tseslint from 'typescript-eslint'
import eslintConfigPrettier from 'eslint-config-prettier'

export default tseslint.config(
  { ignores: ['dist', 'node_modules'] },
  {
    extends: [js.configs.recommended, ...tseslint.configs.recommended],
    files: ['**/*.{ts,tsx}'],
    languageOptions: {
      ecmaVersion: 2022,
      globals: globals.browser,
    },
    plugins: { 'react-hooks': reactHooks },
    rules: {
      ...reactHooks.configs.recommended.rules,
      '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
      // All network calls must go through the shared API client (fleet
      // transport, `@hideyukimori/nene2-client`), which applies the
      // Authorization + X-Authorization mirror (#265). A raw fetch() drops
      // the mirror and 401s behind the shared-hosting proxy (#312).
      'no-restricted-syntax': [
        'error',
        {
          selector: "CallExpression[callee.name='fetch']",
          message:
            'Do not call fetch() directly — use `api` from src/api/client.ts so the Authorization/X-Authorization mirror is sent (#265, #312).',
        },
      ],
    },
  },
  {
    // The shared API client is the single sanctioned place that calls fetch().
    files: ['src/api/client.ts'],
    rules: { 'no-restricted-syntax': 'off' },
  },
  eslintConfigPrettier,
)
