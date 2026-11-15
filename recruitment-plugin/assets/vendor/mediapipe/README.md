# MediaPipe Face Detection assets

- Runtime: `@mediapipe/face_detection` 0.4.1646425229
- License: Apache-2.0 (see [`LICENSE.txt`](./LICENSE.txt))
- The short-range face model, graph, and WebAssembly runtime are bundled from
  that npm package. The browser loads these files from this site; camera frames
  are processed locally and are not sent to a model service.
- Keep the runtime and its `.tflite`, `.binarypb`, and `*_wasm_bin.*` assets
  together when updating or packaging the plugin.

The detector reports face locations/count and six facial keypoints (including
eye and nose points). The plugin uses their relative positions only for a
coarse, local face-orientation alert; this is not gaze tracking or identity
matching. It does not identify a person or record video.
