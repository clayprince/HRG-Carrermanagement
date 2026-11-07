// public.js - Frontend JavaScript for the Recruitment Plugin public area.
(function recruitmentPublicScripts() {
  if (window.RecruitmentPublicScripts) {
    return;
  }
  window.RecruitmentPublicScripts = true;
  document.documentElement.classList.add("js");
  setupPortalRole();
  setupApplicationWizard();
  setupApplicationHistoryControls();
  setupConsentButton();
  setupScreenExplorer();
  setupApplicantAccountMenu();
  setupAccountConfirmationRedirect();
  setupApplicantProfileRepeater();
  setupPsychologicalTestProcessing();
  setupPsychotestDeviceCheck();
  setupPsychometricAssessments();

  document.addEventListener("click", function handleRecruitmentClick(event) {
    const removeButton = event.target.closest("[data-remove-section]");
    if (removeButton) {
      const section = removeButton.closest("[data-repeatable]");
      if (section) {
        section.remove();
        renumberSections(removeButton.dataset.removeSection);
      }
      return;
    }

    const addButton = event.target.closest("[data-add]");
    if (!addButton) {
      return;
    }

    const type = addButton.dataset.add;
    const repeatable = document.querySelector(`[data-repeatable="${type}"]`);
    if (!repeatable) {
      return;
    }

    const clone = repeatable.cloneNode(true);
    const count =
      document.querySelectorAll(`[data-repeatable="${type}"]`).length + 1;
    const removeControl = document.createElement("button");
    removeControl.type = "button";
    removeControl.className = "daw-recruitment__remove-row";
    removeControl.dataset.removeSection = type;
    removeControl.textContent = "Hapus";
    clone.querySelector(".daw-recruitment__repeatable-title").textContent =
      `${type === "education" ? "PENDIDIKAN" : "PEKERJAAN"} ${count}`;
    clone
      .querySelector(".daw-recruitment__repeatable-title")
      .appendChild(removeControl);
    clone
      .querySelectorAll("input, select, textarea")
      .forEach(function clearField(field) {
        field.value = "";
        if (
          field.type === "file" ||
          field.type === "checkbox" ||
          field.type === "radio"
        ) {
          field.checked = false;
        }
      });
    repeatable.parentNode.insertBefore(clone, addButton);
  });

  function renumberSections(type) {
    document
      .querySelectorAll(`[data-repeatable="${type}"]`)
      .forEach(function updateSectionTitle(section, index) {
        const title = section.querySelector(
          ".daw-recruitment__repeatable-title",
        );
        const label = type === "education" ? "PENDIDIKAN" : "PEKERJAAN";
        const removeControl = title.querySelector("[data-remove-section]");
        title.textContent = `${label} ${index + 1}`;
        if (removeControl) {
          title.appendChild(removeControl);
        }
      });
  }

  function setupPortalRole() {
    const roleSelect = document.querySelector("[data-portal-role]");
    const forgotLink = document.querySelector("[data-portal-forgot]");
    const forgotModal = document.querySelector("[data-portal-forgot-modal]");
    if (!roleSelect || !forgotLink || !forgotModal) {
      return;
    }

    const updateForgotLink = function updateForgotLink() {
      const isDepartment = "user_department" === roleSelect.value;
      forgotLink.href =
        isDepartment
          ? roleSelect.dataset.forgotDepartment
          : roleSelect.dataset.forgotHr;
      forgotModal.querySelector("[data-portal-forgot-department]").hidden =
        !isDepartment;
      forgotModal.querySelector("[data-portal-forgot-hr]").hidden =
        isDepartment;
    };

    const openForgotModal = function openForgotModal(event) {
      event.preventDefault();
      updateForgotLink();
      forgotModal.showModal();
    };

    roleSelect.addEventListener("change", updateForgotLink);
    forgotLink.addEventListener("click", openForgotModal);
    forgotModal
      .querySelector("[data-portal-forgot-close]")
      .addEventListener("click", function closeForgotModal() {
        forgotModal.close();
      });
    updateForgotLink();

    if ("true" === forgotModal.dataset.autoOpen) {
      forgotModal.showModal();
    }
  }

  function setupApplicationWizard() {
    const form = document.querySelector("[data-application-wizard]");
    if (!form) {
      return;
    }

    const cards = [...form.querySelectorAll(".daw-recruitment__form-card")];
    const actions = form.querySelector(".daw-recruitment__form-actions");
    if (cards.length < 2 || !actions) {
      return;
    }

    const stepLabels = cards.map(function getStepLabel(card, index) {
      const heading = card.querySelector("h2");
      return heading ? heading.textContent.trim() : `Tahap ${index + 1}`;
    });
    const progress = document.createElement("ol");
    progress.className = "daw-application-wizard__progress";
    progress.setAttribute("aria-label", "Tahapan formulir lamaran");
    const panels = stepLabels.map(function createStep(label, index) {
      const step = index + 1;
      const item = document.createElement("li");
      item.dataset.applicationStepIndicator = String(step);
      const number = document.createElement("span");
      number.textContent = String(step);
      const title = document.createElement("strong");
      title.textContent = label;
      item.append(number, title);
      progress.appendChild(item);

      const panel = document.createElement("div");
      panel.className = "daw-application-wizard__panel";
      panel.dataset.applicationStepPanel = String(step);
      panel.hidden = step !== 1;
      return panel;
    });

    const navigation = document.createElement("div");
    navigation.className = "daw-application-wizard__navigation";
    const back = document.createElement("button");
    back.type = "button";
    back.className = "daw-recruitment__button daw-recruitment__button--muted";
    back.textContent = "Kembali";
    back.hidden = true;
    const next = document.createElement("button");
    next.type = "button";
    next.className = "daw-recruitment__button";
    next.textContent = "Lanjut";
    navigation.append(back, next);

    form.insertBefore(progress, cards[0]);
    cards.forEach(function placeCard(card, index) {
      panels[index].appendChild(card);
    });
    panels.forEach((panel) => form.insertBefore(panel, actions));
    panels[panels.length - 1].appendChild(actions);
    form.insertBefore(navigation, panels[0]);

    let activeStep = 1;
    const renderStep = function renderStep(step) {
      activeStep = step;
      panels.forEach((panel, index) => {
        panel.hidden = index + 1 !== activeStep;
      });
      progress.querySelectorAll("[data-application-step-indicator]").forEach((item) => {
        const itemStep = Number(item.dataset.applicationStepIndicator);
        item.classList.toggle("is-active", itemStep === activeStep);
        item.classList.toggle("is-complete", itemStep < activeStep);
      });
      back.hidden = activeStep === 1;
      next.hidden = activeStep === panels.length;
      form.scrollIntoView({ behavior: "smooth", block: "start" });
    };

    next.addEventListener("click", function goToNextStep() {
      const invalidField = panels[activeStep - 1].querySelector(":invalid");
      if (invalidField) {
        invalidField.reportValidity();
        return;
      }
      renderStep(Math.min(activeStep + 1, panels.length));
    });
    back.addEventListener("click", function goToPreviousStep() {
      renderStep(Math.max(activeStep - 1, 1));
    });
    form.addEventListener("submit", function revealInvalidStep(event) {
      const invalidField = form.querySelector(":invalid");
      if (!invalidField) {
        return;
      }
      const panel = invalidField.closest("[data-application-step-panel]");
      if (panel && Number(panel.dataset.applicationStepPanel) !== activeStep) {
        event.preventDefault();
        renderStep(Number(panel.dataset.applicationStepPanel));
        invalidField.reportValidity();
      }
    });
    renderStep(activeStep);
  }

  function setupApplicationHistoryControls() {
    const filters = document.querySelectorAll("[data-application-filter]");
    const search = document.querySelector("[data-application-search]");
    const rows = [...document.querySelectorAll("[data-application-state]")];
    if (!filters.length || !search || !rows.length) {
      return;
    }

    let activeFilter = "all";
    const updateRows = function updateRows() {
      const query = search.value.trim().toLowerCase();
      rows.forEach((row) => {
        const matchesFilter = "all" === activeFilter
          || activeFilter === row.dataset.applicationState
          || ("finished" === activeFilter && ["accepted", "rejected"].includes(row.dataset.applicationState));
        const matchesSearch = (row.dataset.applicationSearch || "").includes(query);
        row.hidden = !matchesFilter || !matchesSearch;
      });
    };

    filters.forEach((button) => {
      button.addEventListener("click", function setApplicationFilter() {
        activeFilter = button.dataset.applicationFilter;
        filters.forEach((filter) => {
          const isActive = filter === button;
          filter.classList.toggle("is-active", isActive);
          filter.setAttribute("aria-pressed", String(isActive));
        });
        updateRows();
      });
    });
    search.addEventListener("input", updateRows);
  }

  function setupConsentButton() {
    const submitButton = document.querySelector("[data-submit-application]");
    const applicationForm = submitButton ? submitButton.closest("form") : null;
    const consentCheckboxes = document.querySelectorAll("[data-pdp-consent]");
    if (!submitButton || !applicationForm || !consentCheckboxes.length) {
      return;
    }

    const updateSubmitState = function updateSubmitState() {
      const allConsented = [...consentCheckboxes].every(
        (checkbox) => checkbox.checked,
      );
      const previewOnly = "true" === applicationForm.dataset.previewOnly;
      submitButton.disabled = previewOnly || !allConsented;
      submitButton.setAttribute("aria-disabled", String(previewOnly || !allConsented));
    };

    consentCheckboxes.forEach((checkbox) =>
      checkbox.addEventListener("change", updateSubmitState),
    );
    applicationForm.addEventListener("submit", function lockSubmitButton() {
      if (submitButton.disabled) {
        return;
      }
      submitButton.disabled = true;
      submitButton.textContent = "Submitting...";
    });
    updateSubmitState();
  }

  function setupScreenExplorer() {
    const explorer = document.querySelector("[data-screen-explorer]");
    if (!explorer) {
      return;
    }

    const toggle = explorer.querySelector("[data-screen-explorer-toggle]");
    const close = explorer.querySelector("[data-screen-explorer-close]");
    const panel = explorer.querySelector(".daw-screen-explorer__panel");
    const setOpen = function setOpen(isOpen) {
      panel.hidden = !isOpen;
      toggle.setAttribute("aria-expanded", String(isOpen));
      explorer.classList.toggle("is-open", isOpen);
    };

    toggle.addEventListener("click", function toggleExplorer() {
      setOpen(panel.hidden);
    });
    close.addEventListener("click", function closeExplorer() {
      setOpen(false);
    });
    document.addEventListener(
      "keydown",
      function closeExplorerWithEscape(event) {
        if ("Escape" === event.key) {
          setOpen(false);
        }
      },
    );

    const groupButtons = explorer.querySelectorAll(
      "[data-screen-explorer-group-toggle]",
    );
    groupButtons.forEach(function groupButton(groupButtonElement) {
      const parentGroup = groupButtonElement.closest(
        ".daw-screen-explorer__group",
      );
      const body = parentGroup
        ? parentGroup.querySelector(".daw-screen-explorer__group-body")
        : null;
      if (!parentGroup || !body) {
        return;
      }
      groupButtonElement.addEventListener("click", function toggleGroup() {
        const isOpen = parentGroup.classList.contains("is-open");
        parentGroup.classList.toggle("is-open", !isOpen);
        parentGroup.classList.toggle("is-collapsed", isOpen);
        groupButtonElement.setAttribute("aria-expanded", String(!isOpen));
        body.hidden = isOpen;
      });
    });
  }

  function setupAccountConfirmationRedirect() {
    const notice = document.querySelector("[data-account-confirmation-redirect]");
    if (!notice) {
      return;
    }

    window.setTimeout(function redirectToApplicantProfile() {
      window.location.assign(notice.dataset.accountConfirmationRedirect);
    }, 1400);
  }

  function setupApplicantProfileRepeater() {
    document.querySelectorAll("[data-profile-collection]").forEach(syncProfileRowRequirements);
    document.addEventListener("input", function validateProfileRow(event) {
      const section = event.target.closest("[data-profile-collection]");
      if (section) {
        syncProfileRowRequirements(section);
      }
    });
    document.addEventListener("change", function validateProfileRow(event) {
      const section = event.target.closest("[data-profile-collection]");
      if (section) {
        syncProfileRowRequirements(section);
      }
    });

    document.addEventListener("click", function handleProfileRepeaterClick(event) {
      const addTableRowButton = event.target.closest("[data-add-application-table-row]");
      if (addTableRowButton) {
        const tableId = addTableRowButton.dataset.addApplicationTableRow;
        const tableBody = document.querySelector(`[data-application-table-body="${tableId}"]`);
        const nextRow = tableBody?.querySelector("[data-application-table-row][hidden]");
        if (!nextRow) {
          addTableRowButton.disabled = true;
          return;
        }

        nextRow.hidden = false;
        nextRow.querySelectorAll("input, select, textarea").forEach(function enableApplicationTableField(field) {
          field.disabled = false;
        });
        if (!tableBody.querySelector("[data-application-table-row][hidden]")) {
          addTableRowButton.disabled = true;
        }
        return;
      }

      const addButton = event.target.closest("[data-add-profile-row]");
      if (addButton) {
        const collection = addButton.dataset.addProfileRow;
        const section = document.querySelector(`[data-profile-collection="${collection}"]`);
        const template = section?.querySelector(`[data-profile-template="${collection}"]`);
        if (!section || !template) {
          return;
        }

        const rowCount = section.querySelectorAll("[data-profile-row]").length;
        const maximum = Number(addButton.dataset.maxProfileRows || 0);
        if (maximum && rowCount >= maximum) {
          addButton.disabled = true;
          return;
        }

        const indices = [...section.querySelectorAll("[data-profile-row] [name]")]
          .map(function getProfileRowIndex(field) {
            const match = field.name.match(/\[(\d+)\]/);
            return match ? Number(match[1]) : -1;
          })
          .filter((index) => index >= 0);
        const index = indices.length ? Math.max(...indices) + 1 : 0;
        const fragment = template.content.cloneNode(true);
        fragment.querySelectorAll("[name]").forEach(function assignProfileRowIndex(field) {
          field.name = field.name.replace(/__INDEX__/g, String(index));
        });
        const targetContainer = section.querySelector("[data-profile-tbody]") || template.parentElement || section;
        targetContainer.insertBefore(fragment, template);
        updateProfileRowNumbers(section);
        return;
      }

      const removeButton = event.target.closest("[data-remove-profile-row]");
      if (!removeButton) {
        return;
      }
      const row = removeButton.closest("[data-profile-row]");
      const section = removeButton.closest("[data-profile-collection]");
      if (row && section) {
        row.remove();
        updateProfileRowNumbers(section);
      }
    });

    document.querySelectorAll("[data-profile-collection]").forEach(updateProfileRowNumbers);
  }

  function syncProfileRowRequirements(section) {
    section.querySelectorAll("[data-profile-row]").forEach(function validateProfileRow(row) {
      const fields = [...row.querySelectorAll("[name]")];
      const hasValue = fields.some((field) => field.value.trim() !== "");
      fields.forEach(function requireCompletedProfileRow(field) {
        field.required = hasValue;
      });
    });
  }

  function updateProfileRowNumbers(section) {
    const rows = section.querySelectorAll("[data-profile-row]");
    const collection = section.dataset.profileCollection;
    const isTable = Boolean(section.querySelector("table"));
    const label = "social_media" === collection ? "Media Sosial" : (isTable ? "" : "Pengalaman");
    rows.forEach(function numberProfileRow(row, index) {
      const number = row.querySelector("[data-row-number]");
      if (number) {
        number.textContent = label ? `${label} ${index + 1}` : String(index + 1);
      }
    });
    const addButton = section.querySelector("[data-add-profile-row]");
    if (addButton && "1" !== addButton.dataset.profileReadonly) {
      const maximum = Number(addButton.dataset.maxProfileRows || 0);
      addButton.disabled = maximum > 0 && rows.length >= maximum;
    }
  }

  function setupApplicantAccountMenu() {
    const menu = document.querySelector("[data-account-menu]");
    if (!menu) {
      return;
    }

    const toggle = menu.querySelector("[data-account-menu-toggle]");
    const panel = menu.querySelector("[data-account-menu-panel]");
    if (!toggle || !panel) {
      return;
    }

    const setOpen = function setAccountMenuOpen(isOpen) {
      panel.hidden = !isOpen;
      toggle.setAttribute("aria-expanded", String(isOpen));
    };

    toggle.addEventListener("click", function toggleAccountMenu() {
      setOpen(panel.hidden);
    });
    document.addEventListener("click", function closeAccountMenu(event) {
      if (!menu.contains(event.target)) {
        setOpen(false);
      }
    });
    document.addEventListener("keydown", function closeAccountMenuOnEscape(event) {
      if ("Escape" === event.key) {
        setOpen(false);
        toggle.focus();
      }
    });
  }

  function setupPsychologicalTestProcessing() {
    const processingCard = document.querySelector("[data-psych-processing]");
    if (!processingCard) {
      return;
    }

    const nextUrl = processingCard.dataset.nextUrl;
    const progressBar = processingCard.querySelector(
      "[data-psych-progress-bar]",
    );
    const progressValue = processingCard.querySelector(
      "[data-psych-progress-value]",
    );
    const progressTrack = processingCard.querySelector('[role="progressbar"]');
    if (!nextUrl || !progressBar || !progressValue || !progressTrack) {
      return;
    }

    let progress = 0;
    const updateProgress = function updateProgress() {
      progress = Math.min(progress + 1, 100);
      progressBar.style.width = `${progress}%`;
      progressValue.textContent = `${progress}%`;
      progressTrack.setAttribute("aria-valuenow", String(progress));

      if (progress < 100) {
        window.setTimeout(updateProgress, 35);
        return;
      }

      window.setTimeout(function advancePsychologicalTest() {
        window.location.href = nextUrl;
      }, 500);
    };

    updateProgress();
  }

  function setupPsychometricAssessments() {
    setupIqAssessment();
    setupPauliAssessment();
    setupPersonalityAssessment();
  }

  function setupIqAssessment() {
    const root = document.querySelector("[data-iq-assessment]");
    if (!root) {
      return;
    }

    const form = root.querySelector("[data-iq-form]");
    const status = root.querySelector("[data-iq-save-status]");
    const timer = root.querySelector("[data-iq-timer]");
    if (!form || !status || !timer) {
      return;
    }

    const pendingTimers = new Map();
    let saveQueue = Promise.resolve();
    const saveQuestion = function saveQuestion(question) {
      const inputs = question.querySelectorAll("[data-iq-answer]:checked");
      const values = new URLSearchParams();
      values.set("action", "recruitment_iq_save_answer");
      values.set("nonce", root.dataset.saveNonce || "");
      values.set("question_id", question.dataset.iqQuestion || "");
      inputs.forEach(function appendAnswer(input, index) {
        values.set(`selected_answers[${index}]`, input.value);
      });
      status.textContent = "Menyimpan jawaban...";
      saveQueue = saveQueue
        .catch(function continueAfterPreviousSaveFailure() {})
        .then(function sendAnswer() {
          return postAssessment(root.dataset.ajaxUrl, values);
        })
        .then(function savedAnswer() {
          status.textContent = "Jawaban tersimpan.";
        })
        .catch(function reportSaveError(error) {
          status.textContent = error.message;
          status.setAttribute("data-save-error", "true");
          throw error;
        });
      return saveQueue;
    };

    root.addEventListener("change", function saveIqAnswer(event) {
      if (!event.target.matches("[data-iq-answer]")) {
        return;
      }
      const question = event.target.closest("[data-iq-question]");
      if (!question) {
        return;
      }
      if (
        event.target.checked &&
        question.querySelectorAll("[data-iq-answer]:checked").length >
          Number(question.dataset.maxSelect || 1)
      ) {
        event.target.checked = false;
        status.textContent = `Pilih paling banyak ${question.dataset.maxSelect || 1} jawaban untuk soal ini.`;
        return;
      }
      window.clearTimeout(pendingTimers.get(question));
      pendingTimers.set(
        question,
        window.setTimeout(function enqueueAnswer() {
          pendingTimers.delete(question);
          saveQuestion(question).catch(function keepFailureVisible() {});
        }, 250),
      );
    });

    form.addEventListener("submit", function waitForIqSaves(event) {
      event.preventDefault();
      pendingTimers.forEach(function flushTimer(timerId, question) {
        window.clearTimeout(timerId);
        saveQuestion(question);
      });
      pendingTimers.clear();
      saveQueue
        .catch(function allowServerToRetryIqAnswers() {})
        .then(function submitIqForm() {
          HTMLFormElement.prototype.submit.call(form);
        })
        .catch(function preventIncompleteSubmit() {});
    });

    const deadline = Number(root.dataset.deadline || 0);
    const updateTimer = function updateIqTimer() {
      const remaining = Math.max(0, deadline - Math.floor(Date.now() / 1000));
      const minutes = Math.floor(remaining / 60);
      const seconds = remaining % 60;
      timer.textContent = `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
      if (remaining === 0) {
        form.requestSubmit();
      } else {
        window.setTimeout(updateTimer, 1000);
      }
    };
    updateTimer();
  }

  function setupPauliAssessment() {
    const root = document.querySelector("[data-pauli-assessment]");
    if (!root) {
      return;
    }

    const form = root.querySelector("[data-pauli-assessment-form]");
    const payload = root.querySelector("[data-pauli-answer-payload]");
    const status = root.querySelector("[data-pauli-save-status]");
    const answers = root.querySelectorAll("[data-pauli-answer]");
    if (!form || !payload || !status || !answers.length) {
      return;
    }

    let saveTimer = 0;
    let hasUnflushedInput = false;
    let saveQueue = Promise.resolve();
    const saveColumn = function saveColumn() {
      const value = Array(99).fill("-");
      answers.forEach(function collectPauliAnswer(input) {
        const index = Number(input.dataset.answerIndex);
        if (Number.isInteger(index) && index >= 0 && index < value.length) {
          value[index] = /^[0-9]$/.test(input.value) ? input.value : "-";
        }
      });
      payload.value = value.join("");
      const body = new URLSearchParams({
        action: "recruitment_pauli_save_column",
        nonce: root.dataset.saveNonce || "",
        segment: root.dataset.segment || "",
        answers: payload.value,
      });
      status.textContent = "Menyimpan jawaban kolom...";
      saveQueue = saveQueue
        .catch(function continueAfterPreviousSaveFailure() {})
        .then(function sendColumn() {
          return postAssessment(root.dataset.ajaxUrl, body);
        })
        .then(function savedColumn() {
          hasUnflushedInput = false;
          status.textContent = "Jawaban kolom tersimpan.";
        })
        .catch(function reportColumnError(error) {
          hasUnflushedInput = true;
          status.textContent = error.message;
          throw error;
        });
      return saveQueue;
    };

    root.addEventListener("input", function schedulePauliSave(event) {
      if (!event.target.matches("[data-pauli-answer]")) {
        return;
      }
      hasUnflushedInput = true;
      event.target.value = event.target.value.replace(/[^0-9]/g, "").slice(0, 1);
      window.clearTimeout(saveTimer);
      saveTimer = window.setTimeout(function persistPauliColumn() {
        saveColumn().catch(function keepFailureVisible() {});
      }, 350);
    });

    const segmentDeadline = Number(root.dataset.segmentDeadline || 0);
    const refreshAtBoundary = function refreshPauliColumn() {
      if (segmentDeadline <= Math.floor(Date.now() / 1000)) {
        if (saveTimer || hasUnflushedInput) {
          window.clearTimeout(saveTimer);
          saveColumn()
            .then(function reloadAfterSave() {
              window.location.reload();
            })
            .catch(function keepFailureVisible() {});
        } else {
          window.location.reload();
        }
        return;
      }
      window.setTimeout(refreshAtBoundary, 1000);
    };
    refreshAtBoundary();

    form.addEventListener("submit", function submitPauliColumn(event) {
      event.preventDefault();
      window.clearTimeout(saveTimer);
      saveColumn()
        .catch(function allowServerToRetryPauliAnswers() {})
        .then(function advancePauliColumn() {
          HTMLFormElement.prototype.submit.call(form);
        })
        .catch(function keepFailureVisible() {});
    });
  }

  function setupPersonalityAssessment() {
    const root = document.querySelector("[data-personality-assessment]");
    if (!root) {
      return;
    }

    const form = root.querySelector("[data-personality-form]");
    const status = root.querySelector("[data-personality-save-status]");
    const progress = root.querySelector("[data-personality-progress]");
    if (!form || !status || !progress) {
      return;
    }

    const pendingTimers = new Map();
    const savedQuestions = new Set();
    root.querySelectorAll("[data-personality-question]").forEach(function trackExistingAnswer(question) {
      if (question.querySelector("[data-personality-answer]:checked")) {
        savedQuestions.add(question.dataset.personalityQuestion || "");
      }
    });
    let saveQueue = Promise.resolve();
    const saveQuestion = function saveQuestion(question) {
      const questionId = question.dataset.personalityQuestion || "";
      const values = new URLSearchParams({
        action: "recruitment_personality_save_answer",
        nonce: root.dataset.saveNonce || "",
        test: root.dataset.testKey || "",
        question_id: questionId,
      });
      question
        .querySelectorAll("[data-personality-answer]:checked")
        .forEach(function appendPersonalityAnswer(input) {
          values.set(`answer[${input.dataset.answerPart}]`, input.value);
        });
      status.textContent = "Menyimpan jawaban...";
      saveQueue = saveQueue
        .catch(function continueAfterPreviousSaveFailure() {})
        .then(function sendPersonalityAnswer() {
          return postAssessment(root.dataset.ajaxUrl, values);
        })
        .then(function savedPersonalityAnswer() {
          savedQuestions.add(questionId);
          progress.textContent = String(savedQuestions.size);
          status.textContent = "Jawaban tersimpan.";
        })
        .catch(function reportPersonalityError(error) {
          status.textContent = error.message;
          throw error;
        });
      return saveQueue;
    };

    root.addEventListener("change", function schedulePersonalitySave(event) {
      if (!event.target.matches("[data-personality-answer]")) {
        return;
      }
      const question = event.target.closest("[data-personality-question]");
      if (!question) {
        return;
      }
      window.clearTimeout(pendingTimers.get(question));
      pendingTimers.set(
        question,
        window.setTimeout(function persistPersonalityAnswer() {
          pendingTimers.delete(question);
          saveQuestion(question).catch(function keepFailureVisible() {});
        }, 250),
      );
    });

    form.addEventListener("submit", function waitForPersonalitySaves(event) {
      event.preventDefault();
      pendingTimers.forEach(function flushTimer(timerId, question) {
        window.clearTimeout(timerId);
        saveQuestion(question);
      });
      pendingTimers.clear();
      saveQueue
        .catch(function allowServerToRetryPersonalityAnswers() {})
        .then(function submitPersonalityForm() {
          HTMLFormElement.prototype.submit.call(form);
        })
        .catch(function preventIncompleteSubmit() {});
    });

    const reminderMinutes = Number(root.dataset.reminderMinutes || 0);
    const startedAt = Number(root.dataset.startedAt || 0);
    const elapsed = root.querySelector("[data-personality-elapsed]");
    const reminder = root.querySelector("[data-personality-reminder]");
    if (elapsed && startedAt) {
      const updateElapsed = function updateElapsedTime() {
        const seconds = Math.max(0, Math.floor(Date.now() / 1000) - startedAt);
        elapsed.textContent = `${String(Math.floor(seconds / 60)).padStart(2, "0")}:${String(seconds % 60).padStart(2, "0")}`;
        if (reminder && reminderMinutes && seconds >= reminderMinutes * 60) {
          reminder.hidden = false;
          reminder.textContent = `Pengingat: standar waktu pengerjaan ${reminderMinutes} menit telah tercapai. Anda dapat melanjutkan hingga seluruh soal selesai.`;
        }
        window.setTimeout(updateElapsedTime, 1000);
      };
      updateElapsedTime();
    }
  }

  async function postAssessment(url, body) {
    if (!url) {
      throw new Error("Alamat penyimpanan jawaban tidak tersedia.");
    }
    const response = await fetch(url, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: body.toString(),
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(
        result.data && result.data.message
          ? result.data.message
          : "Jawaban gagal disimpan. Periksa koneksi lalu coba lagi.",
      );
    }
    return result;
  }

  function setupPsychotestDeviceCheck() {
    const deviceCheck = document.querySelector(
      "[data-psychotest-device-check]",
    );
    if (!deviceCheck) {
      return;
    }

    const cameraStatus = document.querySelector("[data-device-camera-status]");
    const microphoneStatus = document.querySelector(
      "[data-device-microphone-status]",
    );
    const audioStatus = document.querySelector("[data-device-audio-status]");
    const browserStatus = document.querySelector(
      "[data-device-browser-status]",
    );
    const connectionStatus = document.querySelector(
      "[data-device-connection-status]",
    );
    const permissionButton = document.querySelector("[data-device-permission]");
    const retryButton = document.querySelector("[data-device-retry]");
    const continueButton = document.querySelector("[data-device-continue]");
    const videoElement = document.querySelector("[data-device-video]");
    const cameraShell = document.querySelector("[data-device-camera-shell]");
    const emptyState = document.querySelector("[data-device-empty]");
    const unsupportedBanner = document.querySelector(
      "[data-device-browser-banner]",
    );
    if (
      !cameraStatus ||
      !microphoneStatus ||
      !audioStatus ||
      !browserStatus ||
      !connectionStatus ||
      !permissionButton ||
      !retryButton ||
      !continueButton ||
      !videoElement ||
      !cameraShell ||
      !emptyState ||
      !unsupportedBanner
    ) {
      return;
    }

    let stream = null;

    const updateStatus = function updateStatus(
      statusNode,
      state,
      title,
      message,
    ) {
      statusNode.classList.remove("is-success", "is-warning", "is-error");
      statusNode.classList.add(
        state === "success"
          ? "is-success"
          : state === "error"
            ? "is-error"
            : "is-warning",
      );
      const icon = statusNode.querySelector("[data-device-status-icon]");
      const titleNode = statusNode.querySelector("[data-device-status-title]");
      const copyNode = statusNode.querySelector("[data-device-status-copy]");

      if (icon) {
        icon.textContent =
          state === "success" ? "✓" : state === "error" ? "!" : "•";
      }
      if (titleNode) {
        titleNode.textContent = title;
      }
      if (copyNode) {
        copyNode.textContent = message;
      }
    };

    const setContinueState = function setContinueState(isReady) {
      continueButton.disabled = !isReady;
      continueButton.setAttribute("aria-disabled", String(!isReady));
    };

    const stopStream = function stopStream() {
      if (!stream) {
        return;
      }

      stream.getTracks().forEach(function stopTrack(track) {
        track.stop();
      });
      stream = null;
      videoElement.srcObject = null;
      videoElement.pause();
      videoElement.hidden = true;
      cameraShell.classList.add("is-empty");
      emptyState.hidden = false;
    };

    const handleUnsupportedBrowser = function handleUnsupportedBrowser() {
      unsupportedBanner.hidden = false;
      updateStatus(
        browserStatus,
        "error",
        "Browser tidak mendukung",
        "Gunakan browser modern seperti Chrome atau Edge untuk menjalankan tes.",
      );
      updateStatus(
        cameraStatus,
        "error",
        "Kamera tidak tersedia",
        "Browser ini tidak mendukung akses kamera dan mikrofon.",
      );
      permissionButton.disabled = true;
      permissionButton.textContent = "Browser Tidak Didukung";
      setContinueState(false);
    };

    const setNetworkStatus = function setNetworkStatus() {
      if (navigator.onLine) {
        updateStatus(
          connectionStatus,
          "success",
          "Koneksi internet siap",
          "Koneksi internet terdeteksi dan stabil untuk melanjutkan tes.",
        );
        return;
      }

      updateStatus(
        connectionStatus,
        "warning",
        "Jaringan tidak stabil",
        "Periksa koneksi internet Anda sebelum melanjutkan tes.",
      );
    };

    const setBrowserStatus = function setBrowserStatus() {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        handleUnsupportedBrowser();
        return;
      }

      unsupportedBanner.hidden = true;
      updateStatus(
        browserStatus,
        "success",
        "Browser siap",
        "Browser mendukung pengecekan kamera dan mikrofon.",
      );
    };

    const handleRequestSuccess = function handleRequestSuccess(mediaStream) {
      stream = mediaStream;
      videoElement.srcObject = mediaStream;
      videoElement.hidden = false;
      cameraShell.classList.remove("is-empty");
      emptyState.hidden = true;
      updateStatus(
        cameraStatus,
        "success",
        "Kamera aktif",
        "Izin akses kamera telah diberikan.",
      );
      updateStatus(
        microphoneStatus,
        "success",
        "Mikrofon aktif",
        "Izin mikrofon telah diberikan.",
      );
      updateStatus(
        audioStatus,
        "success",
        "Audio siap",
        "Audio dan mikrofon terdeteksi dengan baik.",
      );
      permissionButton.disabled = true;
      permissionButton.textContent = "Kamera Sudah Aktif";
      setContinueState(true);
    };

    const handleRequestError = function handleRequestError(error) {
      const name = error && error.name ? error.name : "UnknownError";
      let title = "Kamera belum aktif";
      let message = "Klik “Izinkan Kamera” untuk memulai pengecekan.";

      if ("NotAllowedError" === name) {
        title = "Izin dibatalkan";
        message =
          "Akses kamera dan mikrofon dibatalkan. Silakan izinkan kembali.";
      } else if ("NotFoundError" === name) {
        title = "Kamera tidak ditemukan";
        message = "Hubungkan perangkat kamera lalu coba lagi.";
      } else if ("NotReadableError" === name) {
        title = "Kamera sedang dipakai";
        message =
          "Kamera sedang digunakan oleh perangkat lain. Tutup aplikasi lain dan coba lagi.";
      } else if ("SecurityError" === name) {
        title = "Akses dibatasi";
        message =
          "Halaman ini harus diakses melalui localhost atau HTTPS agar kamera dapat dipakai.";
      }

      updateStatus(cameraStatus, "error", title, message);
      updateStatus(
        microphoneStatus,
        "warning",
        "Mikrofon belum aktif",
        "Izinkan akses mikrofon saat kamera dibuka.",
      );
      updateStatus(
        audioStatus,
        "warning",
        "Audio belum diperiksa",
        "Audio akan diperiksa setelah izin diberikan.",
      );
      permissionButton.disabled = false;
      permissionButton.textContent = "Izinkan Kamera";
      setContinueState(false);
    };

    setBrowserStatus();
    setNetworkStatus();
    setContinueState(false);

    permissionButton.addEventListener("click", function requestCameraAccess() {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        handleUnsupportedBrowser();
        return;
      }

      if (stream) {
        stopStream();
      }

      permissionButton.disabled = true;
      permissionButton.textContent = "Meminta akses...";

      navigator.mediaDevices
        .getUserMedia({
          video: {
            facingMode: "user",
            width: { ideal: 1280 },
            height: { ideal: 720 },
          },
          audio: true,
        })
        .then(handleRequestSuccess)
        .catch(handleRequestError)
        .finally(function resetButtonState() {
          if (!stream) {
            permissionButton.disabled = false;
            permissionButton.textContent = "Izinkan Kamera";
          }
        });
    });

    retryButton.addEventListener("click", function retryCameraCheck() {
      stopStream();
      unsupportedBanner.hidden = true;
      updateStatus(
        cameraStatus,
        "warning",
        "Kamera belum aktif",
        "Klik “Izinkan Kamera” untuk memulai pengecekan.",
      );
      updateStatus(
        microphoneStatus,
        "warning",
        "Mikrofon belum aktif",
        "Izinkan akses mikrofon saat kamera dibuka.",
      );
      updateStatus(
        audioStatus,
        "warning",
        "Audio belum diperiksa",
        "Volume dan input audio akan dicek saat izin diberikan.",
      );
      permissionButton.disabled = false;
      permissionButton.textContent = "Izinkan Kamera";
      setContinueState(false);
    });

    continueButton.addEventListener("click", function continueToNextStep() {
      if (continueButton.disabled) {
        return;
      }
      const nextUrl = continueButton.dataset.nextUrl;
      if (nextUrl) {
        window.location.href = nextUrl;
      }
    });

    window.addEventListener("beforeunload", stopStream);
    document.addEventListener(
      "visibilitychange",
      function onPageVisibilityChanged() {
        if (document.hidden) {
          stopStream();
        }
      },
    );
  }
})();
