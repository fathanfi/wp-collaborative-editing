# wp-collaborative-editing

**This plugin is still under development and considered as experimental.**

This is a WordPress plugin to allows multiple users to collaborate on the same post with block editor.

## Requirements

- PHP >= 7.4
- WordPress >= 5.7
- WordPress Block Editor

## Quick start on your local installation

1. Clone this repository
2. Install dependencies `composer install && npm install`
3. Build dependencies:
   1. `npm run build` for production build,
   2. or `npm run dev` for development build.
4. Start the server `npm run server start`
5. Visit `http://localhost:8888`, default access user: `admin`, password: `password`
6. Create or edit a post inside a block editor, and open another tab or incognito to edit the same post.
7. Optional, you can add another user to edit the same post.

## How it Works

This plugin use [Yjs](https://yjs.dev/) framework, a high-performance [CRDT](https://en.wikipedia.org/wiki/Conflict-free_replicated_data_type) for building collaborative applications that sync automatically.

> It exposes its internal CRDT model as shared data types that can be manipulated concurrently. Shared types are similar to common data types like Map and Array. They can be manipulated, fire events when changes happen, and automatically merge without merge conflicts.

During the initialization, the Gutenberg block editor content state, will be converted into [Y.Doc](https://docs.yjs.dev/api/y.doc)
and the current user state will be set as [Awareness](https://docs.yjs.dev/getting-started/adding-awareness).
These states will be propagated to the other clients through the connection provider.

When the clients listen for changes from the Document Update and Awareness Update, it will be propagated back to the block editor state.
This is called the Editor Binding, the code exists in `assets/src/js/libs/block-editor-binding.js`.

## Connection Providers

Under the hoods, this plugin use Yjs, and Yjs is network agnostic. It can use one or more connection providers to modify the Shared Types.
This plugin, by default use WebSocket connection and can be changed through filter.

### WebSocket

[WebSocket](https://developer.mozilla.org/en-US/docs/Web/API/WebSockets_API) API allows to open a two-way interactive communication session between the user's browser and a server.
This plugin use library from [y-websocket](https://github.com/yjs/y-websocket) to share the Shared Types (Documents & Awareness), across connected clients and use centralized server.

> The Websocket Provider is a solid choice if you want a central source that handles authentication and authorization. Websockets also send header information and cookies, so you can use existing authentication mechanisms with this server.
Supports cross-tab communication. When you open the same document in the same browser, changes on the document are exchanged via cross-tab communication (Broadcast Channel and localStorage as fallback).
Supports the exchange of awareness information (e.g. cursors).

[Read more here](https://docs.yjs.dev/ecosystem/connection-provider/y-websocket).

### WebRTC

[WebRTC (Web Real-Time Communication)](https://developer.mozilla.org/en-US/docs/Web/API/WebRTC_API) API enables Web applications and sites to exchange arbitrary data between browsers without requiring an intermediary, peer-to-peer connection.
This plugin use library from [y-webrtc](https://github.com/yjs/y-webrtc) to share the Shared Types (Documents & Awareness), across connected clients peer-to-peer, without the need of centralized server, although it does need the signaling server to register the clients network.

[Read more here](https://github.com/yjs/y-webrtc).

By Default this plugin use this public WebRTC server provided by the Y Initiatives:
- `wss://signaling.yjs.dev`
- `wss://y-webrtc-signaling-eu.herokuapp.com`
- `wss://y-webrtc-signaling-us.herokuapp.com`

Even though the connection to this signaling server is encrypted, please consider to set up private signaling server yourself.
