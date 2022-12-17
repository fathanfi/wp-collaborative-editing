import { nodeResolve } from '@rollup/plugin-node-resolve';

const rollupConfig = {
	input: 'assets/src/js/server.js',
	output: {
		file: 'assets/dist/server.js',
		format: 'cjs',
		esModule: false,
	},
	plugins: [ nodeResolve() ],
};

export default rollupConfig;
