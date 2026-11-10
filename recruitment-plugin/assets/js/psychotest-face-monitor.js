const SAMPLE_INTERVAL_MS = 1500;
const FACE_ABSENT_ALERT_MS = 8000;
const MULTIPLE_FACES_ALERT_MS = 2500;

export async function startPsychotestFaceMonitor(options) {
  const { video, runtimeUrl, assetsRoot, onAlert, onError, onStatus } = options;
  await loadFaceDetectionRuntime(runtimeUrl);
  if ("function" !== typeof window.FaceDetection) {
    throw new Error("The local face detection runtime did not initialize.");
  }
  const detector = new window.FaceDetection({
    locateFile: (filename) => new URL(filename, assetsRoot).href,
  });
  detector.setOptions({
    model: "short",
    minDetectionConfidence: 0.65,
    selfieMode: false,
  });
  let latestFaceCount = null;
  detector.onResults((results) => {
    latestFaceCount = results.detections.length;
  });
  await detector.initialize();

  let stopped = false;
  let timer = null;
  let noFaceSince = null;
  let multipleFacesSince = null;
  let lastAlertType = "";
  let lastStatus = "";

  const updateStatus = function updateStatus(message) {
    if (message !== lastStatus) {
      lastStatus = message;
      onStatus(message);
    }
  };

  const stop = function stopFaceMonitor() {
    if (stopped) {
      return;
    }
    stopped = true;
    window.clearTimeout(timer);
    void detector.close();
  };

  const scheduleNextFrame = function scheduleNextFrame() {
    if (!stopped) {
      timer = window.setTimeout(checkFrame, SAMPLE_INTERVAL_MS);
    }
  };

  const checkFrame = async function checkFrame() {
    if (stopped) {
      return;
    }
    const videoTrack = video.srcObject
      ?.getVideoTracks()
      .find((track) => "live" === track.readyState);
    if (!videoTrack) {
      noFaceSince = null;
      multipleFacesSince = null;
      updateStatus("Deteksi wajah dijeda karena kamera tidak aktif.");
      scheduleNextFrame();
      return;
    }
    if (document.hidden || video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA) {
      noFaceSince = null;
      multipleFacesSince = null;
      scheduleNextFrame();
      return;
    }

    try {
      latestFaceCount = null;
      await detector.send({ image: video });
      if (null === latestFaceCount) {
        throw new Error("Face detector returned no result for the video frame.");
      }
      const faceCount = latestFaceCount;
      const now = Date.now();

      if (0 === faceCount) {
        if (null === noFaceSince) {
          noFaceSince = now;
        }
        multipleFacesSince = null;
        updateStatus("Wajah tidak terlihat; sistem menunggu sebelum memberi peringatan.");
        if (
          now - noFaceSince >= FACE_ABSENT_ALERT_MS &&
          "FACE_NOT_DETECTED" !== lastAlertType
        ) {
          lastAlertType = "FACE_NOT_DETECTED";
          updateStatus("Wajah tidak terdeteksi selama beberapa saat; peringatan dikirim.");
          if (false === (await onAlert("FACE_NOT_DETECTED"))) {
            updateStatus("Peringatan wajah tidak terdeteksi belum tersimpan. Periksa koneksi.");
          }
        }
      } else if (1 < faceCount) {
        if (null === multipleFacesSince) {
          multipleFacesSince = now;
        }
        noFaceSince = null;
        updateStatus("Lebih dari satu wajah terlihat; sistem sedang memverifikasi.");
        if (
          now - multipleFacesSince >= MULTIPLE_FACES_ALERT_MS &&
          "MULTIPLE_FACES" !== lastAlertType
        ) {
          lastAlertType = "MULTIPLE_FACES";
          updateStatus("Lebih dari satu wajah terdeteksi; peringatan dikirim untuk ditinjau HR.");
          if (false === (await onAlert("MULTIPLE_FACES"))) {
            updateStatus("Peringatan jumlah wajah belum tersimpan. Periksa koneksi.");
          }
        }
      } else {
        noFaceSince = null;
        multipleFacesSince = null;
        lastAlertType = "";
        updateStatus("Deteksi wajah lokal aktif; satu wajah terdeteksi.");
      }
    } catch (error) {
      stop();
      onError(error);
      return;
    }
    scheduleNextFrame();
  };

  updateStatus("Deteksi wajah lokal aktif; kamera sedang dianalisis di perangkat ini.");
  checkFrame();
  return stop;
}

function loadFaceDetectionRuntime(runtimeUrl) {
  if ("function" === typeof window.FaceDetection) {
    return Promise.resolve();
  }
  return new Promise((resolve, reject) => {
    const script = document.createElement("script");
    script.src = runtimeUrl;
    script.async = true;
    script.onload = resolve;
    script.onerror = () => reject(new Error("Local face detection runtime failed to load."));
    document.head.appendChild(script);
  });
}
