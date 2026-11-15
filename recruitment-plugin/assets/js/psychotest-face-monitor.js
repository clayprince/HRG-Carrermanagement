import { PsychotestViolationStateMachine } from "./psychotest-violation-state-machine.js";

const SAMPLE_INTERVAL_MS = 500;

export async function startPsychotestFaceMonitor(options) {
  const { video, runtimeUrl, assetsRoot, violationConfig, onAlert, onError, onStatus } = options;
  await loadFaceDetectionRuntime(runtimeUrl);
  if ("function" !== typeof window.FaceDetection) {
    throw new Error("The local face detection runtime did not initialize.");
  }

  const detector = new window.FaceDetection({
    locateFile: (filename) => new URL(filename, assetsRoot).href,
  });
  const rules = violationConfig || {};
  const confidenceThreshold = Math.max(
    0.1,
    ...Object.values(rules).map((rule) => rule.confidenceThreshold || 0.1),
  );
  detector.setOptions({
    model: "short",
    minDetectionConfidence: confidenceThreshold,
    selfieMode: false,
  });

  let latestResults = null;
  detector.onResults((results) => {
    latestResults = results;
  });
  await detector.initialize();

  let stopped = false;
  let timer = null;
  let lastStatus = "";
  const stateMachine = new PsychotestViolationStateMachine(rules);

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

  const sample = async function sample(eventType, suspicious, timestamp, confidence = null) {
    const result = stateMachine.observe(eventType, suspicious, timestamp, confidence);
    if (result?.confirmed) {
      if (
        false ===
        (await onAlert(eventType, {
          durationMs: result.durationMs,
          confidence: result.confidence,
        }))
      ) {
        updateStatus("Peringatan belum tersimpan. Periksa koneksi sebelum melanjutkan.");
      }
    }
    return result;
  };

  const markUnknown = function markUnknown(timestamp) {
    for (const eventType of Object.keys(rules)) {
      stateMachine.observe(eventType, null, timestamp);
    }
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
    const timestamp = performance.now();
    const videoTrack = video.srcObject
      ?.getVideoTracks()
      .find((track) => "live" === track.readyState);

    if (!videoTrack) {
      markUnknown(timestamp);
      const result = await sample("CAMERA_STOPPED", true, timestamp);
      if (!result?.confirmed) {
        updateStatus("Kamera terputus; sistem menunggu konfirmasi sebelum mencatat event.");
      }
      scheduleNextFrame();
      return;
    }

    await sample("CAMERA_STOPPED", false, timestamp);
    if (document.hidden || video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA) {
      markUnknown(timestamp);
      scheduleNextFrame();
      return;
    }

    try {
      latestResults = null;
      await detector.send({ image: video });
      if (null === latestResults || !Array.isArray(latestResults.detections)) {
        throw new Error("Face detector returned no result for the video frame.");
      }

      const detections = latestResults.detections;
      const faceCount = detections.length;
      const now = performance.now();
      const faceConfidence = getFaceConfidence(detections);

      await sample("FACE_NOT_DETECTED", 0 === faceCount, now);
      await sample("MULTIPLE_FACES", 1 < faceCount, now, faceConfidence);

      if (1 === faceCount) {
        const headTurnScore = getHeadTurnScore(detections[0]);
        const rule = rules.FACE_TURNED_AWAY;
        const turnedAway =
          null === headTurnScore || null === rule?.signalThreshold
            ? null
            : headTurnScore >= rule.signalThreshold;
        await sample("FACE_TURNED_AWAY", turnedAway, now, faceConfidence);
      } else {
        await sample("FACE_TURNED_AWAY", false, now, faceConfidence);
      }

      const cameraStoppedRule = rules.CAMERA_STOPPED;
      const cameraStoppedResult = stateMachine.observe(
        "CAMERA_STOPPED",
        false,
        now,
      );
      if (cameraStoppedRule && "RECOVERED" === cameraStoppedResult?.phase) {
        updateStatus("Kamera kembali aktif; pemantauan dilanjutkan.");
      } else if (0 === faceCount) {
        updateStatus("Wajah tidak terlihat; sistem sedang memverifikasi durasinya.");
      } else if (1 < faceCount) {
        updateStatus("Lebih dari satu wajah terlihat; sistem sedang memverifikasi durasinya.");
      } else {
        updateStatus(
          null === getHeadTurnScore(detections[0])
            ? "Deteksi wajah lokal aktif; arah wajah tidak tersedia pada perangkat ini."
            : "Deteksi wajah lokal aktif; satu wajah terdeteksi.",
        );
      }
    } catch (error) {
      stop();
      onError(error);
      return;
    }
    scheduleNextFrame();
  };

  updateStatus("Deteksi wajah lokal aktif; analisis berjalan pada perangkat ini.");
  checkFrame();
  return stop;
}

function getFaceConfidence(detections) {
  if (!detections.length) {
    return null;
  }
  const scores = detections
    .map((detection) => {
      const score = detection?.score ?? detection?.confidence;
      if (Array.isArray(score) && Number.isFinite(score[0])) {
        return score[0];
      }
      return Number.isFinite(score) ? score : null;
    })
    .filter((score) => null !== score);
  return scores.length === detections.length ? Math.min(...scores) : null;
}

function getHeadTurnScore(detection) {
  const keypoints = detection?.locationData?.relativeKeypoints;
  if (!Array.isArray(keypoints) || 3 > keypoints.length) {
    return null;
  }
  const firstEye = keypoints[0];
  const secondEye = keypoints[1];
  const nose = keypoints[2];
  if (
    !Number.isFinite(firstEye?.x) ||
    !Number.isFinite(secondEye?.x) ||
    !Number.isFinite(nose?.x)
  ) {
    return null;
  }
  const eyeDistance = Math.abs(firstEye.x - secondEye.x);
  if (0.001 > eyeDistance) {
    return null;
  }
  const eyeCenter = (firstEye.x + secondEye.x) / 2;
  return Math.abs(nose.x - eyeCenter) / eyeDistance;
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
