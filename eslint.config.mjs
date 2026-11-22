// ESLint flat config: catches real bugs (undefined names, duplicates,
// unreachable code, loose equality) and leaves style alone.
import globals from 'globals';

export default [
	{
		ignores: ['node_modules/**', 'vendor/**', 'build/**', '**/*.min.js', 'assets/vendor/**'],
	},
	{
		// Service workers run in a worker scope, and sw.js exports its router
		// for the Vitest suite when loaded as CommonJS.
		files: ['assets/js/sw.js', 'assets/js/sw-retire.js'],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'script',
			globals: { ...globals.serviceworker, module: 'readonly' },
		},
		rules: { 'no-undef': 'error', eqeqeq: ['error', 'smart'] },
	},
	{
		files: ['assets/js/**/*.js'],
		ignores: ['assets/js/sw.js', 'assets/js/sw-retire.js'],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'script',
			globals: {
				...globals.browser,
			},
		},
		rules: {
			'no-undef': 'error',
			'no-dupe-keys': 'error',
			'no-duplicate-case': 'error',
			'no-unreachable': 'error',
			'no-redeclare': 'error',
			eqeqeq: ['error', 'smart'],
			'no-unused-vars': ['warn', { args: 'none' }],
		},
	},
];
