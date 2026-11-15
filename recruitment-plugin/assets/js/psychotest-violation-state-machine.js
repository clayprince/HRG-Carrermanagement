const MAX_OBSERVATION_GAP_MS = 2500;

export class PsychotestViolationStateMachine {
  constructor(config) {
    this.rules = config || {};
    this.states = new Map();
    for (const [eventType, rule] of Object.entries(this.rules)) {
      if (rule && Number.isFinite(rule.thresholdMs)) {
        this.states.set(eventType, this.createState());
      }
    }
  }

  observe(eventType, suspicious, timestamp, confidence = null) {
    const rule = this.rules[eventType];
    const state = this.states.get(eventType);
    if (!rule || !state || !Number.isFinite(timestamp)) {
      return null;
    }

    if (
      true === suspicious &&
      Number.isFinite(confidence) &&
      confidence < rule.confidenceThreshold
    ) {
      suspicious = null;
    }

    if (null === suspicious) {
      if (
        null !== state.lastEvidenceAt &&
        timestamp - state.lastEvidenceAt > MAX_OBSERVATION_GAP_MS &&
        false === state.confirmed
      ) {
        this.resetState(state, "NORMAL");
      }
      return { phase: state.phase, durationMs: state.durationMs };
    }

    if (false === suspicious) {
      if (null === state.startedAt) {
        state.phase = "NORMAL";
        return { phase: state.phase, durationMs: 0 };
      }
      if (null === state.recoveryStartedAt) {
        state.recoveryStartedAt = timestamp;
      }
      state.phase = state.confirmed ? "VIOLATION_CONFIRMED" : "GRACE_PERIOD";
      if (timestamp - state.recoveryStartedAt >= rule.recoveryMs) {
        const durationMs = state.durationMs;
        this.resetState(state, "RECOVERED");
        return { phase: "RECOVERED", durationMs };
      }
      return { phase: state.phase, durationMs: state.durationMs };
    }

    let resumedAfterRecovery = false;
    if (null !== state.recoveryStartedAt) {
      if (timestamp - state.recoveryStartedAt >= rule.recoveryMs) {
        this.resetState(state, "RECOVERED");
      } else {
        state.recoveryStartedAt = null;
        state.lastEvidenceAt = timestamp;
        resumedAfterRecovery = true;
      }
    }

    if (null === state.startedAt) {
      state.startedAt = timestamp;
      state.lastEvidenceAt = timestamp;
      state.durationMs = 0;
      state.phase = "DETECTING";
    } else if (null !== state.lastEvidenceAt && !resumedAfterRecovery) {
      const gap = timestamp - state.lastEvidenceAt;
      if (gap > MAX_OBSERVATION_GAP_MS) {
        if (false === state.confirmed) {
          this.resetState(state, "NORMAL");
          state.startedAt = timestamp;
          state.durationMs = 0;
          state.phase = "DETECTING";
        }
      } else if (0 <= gap) {
        state.durationMs += gap;
      }
      state.lastEvidenceAt = timestamp;
    }

    state.confidence = Number.isFinite(confidence) ? confidence : state.confidence;
    if (false === state.confirmed && state.durationMs >= rule.thresholdMs) {
      state.confirmed = true;
      state.phase = "VIOLATION_CONFIRMED";
      return {
        phase: state.phase,
        durationMs: Math.round(state.durationMs),
        confidence: state.confidence,
        confirmed: true,
      };
    }

    if (state.confirmed) {
      state.phase = "VIOLATION_CONFIRMED";
    } else if (
      0 < rule.graceMs &&
      state.durationMs >= rule.thresholdMs - rule.graceMs
    ) {
      state.phase = "GRACE_PERIOD";
    } else {
      state.phase = "DETECTING";
    }
    return { phase: state.phase, durationMs: Math.round(state.durationMs) };
  }

  reset(eventType) {
    const state = this.states.get(eventType);
    if (state) {
      this.resetState(state, "NORMAL");
    }
  }

  createState() {
    return {
      phase: "NORMAL",
      startedAt: null,
      lastEvidenceAt: null,
      recoveryStartedAt: null,
      durationMs: 0,
      confidence: null,
      confirmed: false,
    };
  }

  resetState(state, phase) {
    Object.assign(state, this.createState(), { phase });
  }
}
