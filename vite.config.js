// See https://vitejs.dev/config/

import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';
import createExternal from 'vite-plugin-external';

const SERVER_HOST = 'localhost';
const SERVER_PORT = 3010;

const PKG_DIR = '/wp-content/plugins/collaborative-editing';
const DIST_DIR = 'assets/dist';

export default defineConfig( ( { command } ) => ( {
	base: command === 'serve' ? '/' : `${ PKG_DIR }/${ DIST_DIR }/`,
	build: {
		manifest: true,
		outDir: DIST_DIR,
		polyfillModulePreload: false,
		rollupOptions: {
			input: '/assets/src/js/main.js',
		},
	},
	plugins: [
		createExternal( {
			externals: {
				'@wordpress/data': 'window.wp.data',
			},
		} ),
		react(),
	],
	server: {
		host: SERVER_HOST,
		origin: `http://${ SERVER_HOST }:${ SERVER_PORT }`,
		port: SERVER_PORT,
		strictPort: true,
		hmr: {
			host: SERVER_HOST,
			port: SERVER_PORT,
			protocol: 'ws',
		},
	},
} ) );
