# MediaPipe Face Detection assets

- Runtime: `@mediapipe/face_detection` 0.4.1646425229
- License: Apache-2.0 (see [`LICENSE.txt`](./LICENSE.txt))
- The short-range face model, graph, and WebAssembly runtime are bundled from
  that npm package. The browser loads these files from this site; camera frames
  are processed locally and are not sent to a model service.
- Keep the runtime and its `.tflite`, `.binarypb`, and `*_wasm_bin.*` assets
  together when updating or packaging the plugin.

The detector reports face presence/count only. It does not identify a person,
perform face matching, or record video.
