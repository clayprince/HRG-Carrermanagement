import test from "node:test";
import assert from "node:assert/strict";
import { PsychotestViolationStateMachine } from "../assets/js/psychotest-violation-state-machine.js";

function createMachine(overrides = {}) {
  return new PsychotestViolationStateMachine({
    LOOKING_AWAY: {
      thresholdMs: 3000,
      graceMs: 1000,
      confidenceThreshold: 0.6,
      recoveryMs: 1000,
      ...overrides,
    },
  });
}

test("brief suspicious behavior recovers without confirmation", () => {
  const machine = createMachine();
  machine.observe("LOOKING_AWAY", true, 0, 0.9);
  machine.observe("LOOKING_AWAY", true, 500, 0.9);
  machine.observe("LOOKING_AWAY", true, 1000, 0.9);
  machine.observe("LOOKING_AWAY", true, 1500, 0.9);

  assert.equal(machine.observe("LOOKING_AWAY", false, 1600, 0.9).phase, "GRACE_PERIOD");
  assert.equal(machine.observe("LOOKING_AWAY", false, 2700, 0.9).phase, "RECOVERED");
});

test("persistent behavior confirms once at its timestamp threshold", () => {
  const machine = createMachine();
  let confirmation = null;
  for (const timestamp of [0, 500, 1000, 1500, 2000, 2500, 3000]) {
    const result = machine.observe("LOOKING_AWAY", true, timestamp, 0.9);
    if (result?.confirmed) {
      confirmation = result;
    }
  }

  assert.equal(confirmation?.phase, "VIOLATION_CONFIRMED");
  assert.equal(confirmation?.durationMs, 3000);
  assert.equal(machine.observe("LOOKING_AWAY", true, 3500, 0.9).confirmed, undefined);
});

test("a brief normal sample does not reset an active detection", () => {
  const machine = createMachine();
  machine.observe("LOOKING_AWAY", true, 0, 0.9);
  machine.observe("LOOKING_AWAY", true, 500, 0.9);
  machine.observe("LOOKING_AWAY", true, 1000, 0.9);
  machine.observe("LOOKING_AWAY", false, 1100, 0.9);
  machine.observe("LOOKING_AWAY", true, 1500, 0.9);

  assert.notEqual(
    machine.observe("LOOKING_AWAY", true, 3000, 0.9)?.confirmed,
    true,
  );
  const result = machine.observe("LOOKING_AWAY", true, 3500, 0.9);
  assert.equal(result?.confirmed, true);
});

test("low confidence samples do not advance or confirm a detection", () => {
  const machine = createMachine();
  machine.observe("LOOKING_AWAY", true, 0, 0.9);
  machine.observe("LOOKING_AWAY", true, 500, 0.9);
  machine.observe("LOOKING_AWAY", true, 1000, 0.4);
  machine.observe("LOOKING_AWAY", true, 1500, 0.4);
  const result = machine.observe("LOOKING_AWAY", true, 2000, 0.4);

  assert.notEqual(result?.confirmed, true);
});
