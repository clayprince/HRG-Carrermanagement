// admin.js — JavaScript for the Recruitment Plugin HRD admin dashboard.
// TODO: Tambahkan interaksi admin seperti konfirmasi delete, filter tabel, dll.
document.documentElement.classList.add("js");

(function recruitmentPsychotestSettings() {
  const settings = document.querySelector("[data-psychotest-settings]");
  if (!settings) {
    return;
  }

  const statusToggle = settings.querySelector("[data-psychotest-status-toggle]");
  const statusLabel = settings.querySelector("[data-psychotest-status-label]");
  const modeSelect = settings.querySelector('[name="global_settings[mode]"]');
  const closingDateLabel = settings.querySelector("[data-closing-date-label]");
  const closingTimeField = settings.querySelector("[data-psychotest-closing-time]");
  const closingTimeInput = closingTimeField
    ? closingTimeField.querySelector("input")
    : null;
  const sectionTitle = settings.querySelector("[data-psychotest-mode-heading]");
  const sectionDescription = settings.querySelector("[data-psychotest-mode-description]");
  const modeBadge = settings.querySelector("[data-psychotest-mode-badge]");

  const updateModeLabels = function updateModeLabels() {
    const online = modeSelect && "online" === modeSelect.value;
    if (closingDateLabel) {
      closingDateLabel.textContent = online
        ? "Tanggal Psikotes Ditutup"
        : "Tanggal Akhir Psikotes";
    }
    if (closingTimeField) {
      closingTimeField.hidden = !online;
    }
    if (closingTimeInput) {
      closingTimeInput.required = Boolean(online && statusToggle && statusToggle.checked);
    }
    if (sectionTitle) {
      sectionTitle.textContent = online
        ? "Jadwal / Informasi Psikotes Online"
        : "Jadwal / Informasi Psikotes Offline";
    }
    if (sectionDescription) {
      sectionDescription.textContent = online
        ? "Atur jadwal, tenggat penutupan, dan informasi pelaksanaan online."
        : "Atur rentang tanggal dan detail pelaksanaan psikotes secara langsung.";
    }
    if (modeBadge) {
      modeBadge.textContent = online ? "Online" : "Offline";
      modeBadge.classList.toggle("daw-psychotest-settings__badge--online", online);
      modeBadge.classList.toggle("daw-psychotest-settings__badge--offline", !online);
    }
  };
  if (statusToggle && statusLabel) {
    const updateStatusLabel = function updateStatusLabel() {
      statusLabel.textContent = statusToggle.checked ? "Aktif" : "Tidak aktif";
    };
    statusToggle.addEventListener("change", function updateStatusAndClosingRequirement() {
      updateStatusLabel();
      updateModeLabels();
    });
    updateStatusLabel();
  }

  if (modeSelect) {
    modeSelect.addEventListener("change", updateModeLabels);
  }
  updateModeLabels();
})();

(function recruitmentEmailConfirmation() {
  const dialog = document.querySelector(".daw-email-confirmation__dialog");
  if (dialog) {
    dialog.focus();
  }
})();

(function recruitmentApplicantTabs() {
  const tabContainer = document.querySelector("[data-applicant-tabs]");
  if (!tabContainer) {
    return;
  }

  const tabButtons = Array.from(
    tabContainer.querySelectorAll("[data-applicant-tab]"),
  );
  const panels = Array.from(
    tabContainer.querySelectorAll("[data-applicant-panel]"),
  );

  tabButtons.forEach(function bindTab(button) {
    button.addEventListener("click", function activateTab() {
      const target = button.dataset.applicantTab;
      tabButtons.forEach(function updateButton(item) {
        const isActive = item === button;
        item.classList.toggle("is-active", isActive);
        item.setAttribute("aria-selected", String(isActive));
      });
      panels.forEach(function updatePanel(panel) {
        const shouldShow = panel.dataset.applicantPanel === target;
        panel.classList.toggle("is-active", shouldShow);
        panel.hidden = !shouldShow;
      });
    });
  });
})();

(function recruitmentAdministrativeDecision() {
  const form = document.querySelector("[data-administrative-decision-form]");
  if (!form) {
    return;
  }

  const decision = form.querySelector('[name="administrative_result"]');
  const nextStageContainer = form.querySelector(
    "[data-administrative-next-stage]",
  );
  const nextStage = form.querySelector('[name="next_stage"]');
  if (!decision || !nextStageContainer || !nextStage) {
    return;
  }

  const updateDecisionLayout = function updateDecisionLayout() {
    const passed = "passed" === decision.value;
    nextStageContainer.hidden = !passed;
    nextStage.disabled = !passed;
    nextStage.required = passed;
    form.classList.toggle("has-next-stage", passed);
    form.classList.toggle("is-failed", "failed" === decision.value);
    if (!passed) {
      nextStage.value = "";
    }
  };

  decision.addEventListener("change", updateDecisionLayout);
  updateDecisionLayout();
})();

(function recruitmentDashboardNavigation() {
  const dashboard = document.querySelector("[data-recruitment-dashboard]");
  if (!dashboard) {
    return;
  }

  const collapseButton = dashboard.querySelector("[data-sidebar-collapse]");
  const navigationLinks = Array.from(
    dashboard.querySelectorAll(
      ".recruitment-dashboard__navigation a[data-dashboard-stage]",
    ),
  );
  const isMobile = window.matchMedia("(max-width: 782px)").matches;
  dashboard.dataset.sidebarCollapsed = String(isMobile);

  const updateCollapseButton = function updateCollapseButton() {
    const isExpanded = "true" !== dashboard.dataset.sidebarCollapsed;
    collapseButton.setAttribute("aria-expanded", String(isExpanded));
    collapseButton.title = isExpanded ? "Ciutkan navigasi" : "Buka navigasi";
  };

  updateCollapseButton();
  collapseButton.addEventListener("click", function toggleSidebar() {
    dashboard.dataset.sidebarCollapsed = String(
      "true" !== dashboard.dataset.sidebarCollapsed,
    );
    updateCollapseButton();
  });

  navigationLinks.forEach(function bindDashboardNavigation(link) {
    link.addEventListener("click", function filterDashboard(event) {
      event.preventDefault();
      const stage = link.dataset.dashboardStage;
      const stageButton = dashboard.querySelector(
        '[data-stage-filter="' + stage + '"]',
      );
      if (stageButton) {
        stageButton.click();
      }
      dashboard
        .querySelectorAll(".recruitment-dashboard__navigation a")
        .forEach(function updateActiveLink(item) {
          item.classList.toggle("is-active", item === link);
          item.removeAttribute("aria-current");
        });
      link.setAttribute("aria-current", "page");
      dashboard
        .querySelector("#candidate-list-title")
        .scrollIntoView({ behavior: "smooth", block: "start" });
    });
  });
})();

(function recruitmentDashboardFilters() {
  const dashboard = document.querySelector("[data-recruitment-dashboard]");
  if (!dashboard) {
    return;
  }

  const stageButtons = Array.from(
    dashboard.querySelectorAll("[data-stage-filter]"),
  );
  const searchInput = dashboard.querySelector("[data-candidate-search]");
  const dealerSelect = dashboard.querySelector("[data-candidate-dealer]");
  const vacancySelect = dashboard.querySelector("[data-candidate-vacancy]");
  const resetButton = dashboard.querySelector("[data-candidate-reset]");
  const emptyMessage = dashboard.querySelector("[data-candidate-empty]");
  const visibleCount = dashboard.querySelector("[data-candidate-count]");
  let activeStage = "all";

  const applyFilters = function applyFilters() {
    const search = searchInput.value.trim().toLocaleLowerCase();
    const cards = Array.from(
      dashboard.querySelectorAll("[data-candidate-card]"),
    );
    let count = 0;

    cards.forEach(function filterCandidate(card) {
      const matchesStage =
        "all" === activeStage || activeStage === card.dataset.stage;
      const matchesSearch = !search || card.dataset.search.includes(search);
      const matchesDealer =
        !dealerSelect.value || dealerSelect.value === card.dataset.dealer;
      const matchesVacancy =
        !vacancySelect.value || vacancySelect.value === card.dataset.vacancy;
      const isVisible =
        matchesStage && matchesSearch && matchesDealer && matchesVacancy;

      card.hidden = !isVisible;
      if (isVisible) {
        count++;
      }
    });

    visibleCount.textContent = String(count);
    emptyMessage.hidden = count > 0;
  };

  stageButtons.forEach(function bindStageFilter(button) {
    button.addEventListener("click", function selectStage() {
      activeStage = button.dataset.stageFilter;
      stageButtons.forEach(function updateStageButton(item) {
        const isActive = item === button;
        item.classList.toggle("is-active", isActive);
        item.setAttribute("aria-pressed", String(isActive));
      });
      applyFilters();
    });
  });

  const requestedStage = new URLSearchParams(window.location.search).get(
    "stage",
  );
  const requestedStageButton = stageButtons.find(
    function findRequestedStage(button) {
      return button.dataset.stageFilter === requestedStage;
    },
  );
  if (requestedStageButton) {
    requestedStageButton.click();
  }

  searchInput.addEventListener("input", applyFilters);
  dealerSelect.addEventListener("change", applyFilters);
  vacancySelect.addEventListener("change", applyFilters);
  resetButton.addEventListener("click", function resetFilters() {
    searchInput.value = "";
    dealerSelect.value = "";
    vacancySelect.value = "";
    stageButtons[0].click();
  });
})();

(function recruitmentInterviewSchedule() {
  const schedule = document.querySelector(".daw-interview-schedule");
  if (!schedule) {
    return;
  }

  const config = schedule.querySelector("[data-schedule-config]");
  const configDateLabel = schedule.querySelector("[data-config-dates]");
  const countLabel = schedule.querySelector("[data-selected-count]");
  const selectedDateLabel = schedule.querySelector("[data-selected-dates]");
  const saveDateCount = schedule.querySelector("[data-save-date-count]");
  const openConfigButton = schedule.querySelector("[data-open-config]");
  const slotList = schedule.querySelector("[data-slot-list]");
  const slotTemplate = schedule.querySelector("[data-schedule-slot-template]");
  const feedback = schedule.querySelector("[data-schedule-feedback]");
  const dateButtons = Array.from(schedule.querySelectorAll("[data-schedule-date]"));
  const selectedDates = new Set();
  const scheduleForm = schedule.querySelector("[data-schedule-form]");
  const datesInput = schedule.querySelector("[data-schedule-dates]");
  const slotsInput = schedule.querySelector("[data-schedule-slots]");

  if (!config || !slotList || !slotTemplate || !feedback || !scheduleForm) {
    return;
  }

  const sortedSelectedDates = function sortedSelectedDates() {
    return Array.from(selectedDates).sort();
  };
  const formatDates = function formatDates(dates) {
    return dates.map(function formatDate(date) {
      const button = dateButtons.find(function findButton(item) {
        return item.dataset.scheduleDate === date;
      });
      return button ? button.dataset.scheduleLabel : date;
    }).join(" · ");
  };
  const updateSelection = function updateSelection() {
    const dates = sortedSelectedDates();
    const dateText = formatDates(dates);
    dateButtons.forEach(function renderDate(button) {
      const selected = selectedDates.has(button.dataset.scheduleDate);
      button.classList.toggle("is-selected", selected);
      button.setAttribute("aria-pressed", String(selected));
    });
    countLabel.textContent = String(dates.length);
    selectedDateLabel.textContent = dateText || "Belum ada tanggal dipilih";
    saveDateCount.textContent = String(dates.length);
    openConfigButton.disabled = 0 === dates.length;
    configDateLabel.textContent = dateText;
    if (0 === dates.length) {
      config.hidden = true;
    }
  };
  const getSlotMode = function getSlotMode(slot) {
    const activeButton = slot.querySelector("[data-slot-mode].is-active");
    return activeButton ? activeButton.dataset.slotMode : "ONLINE";
  };
  const updateSlotLabels = function updateSlotLabels() {
    const slots = Array.from(slotList.querySelectorAll("[data-schedule-slot]"));
    slots.forEach(function updateSlot(slot, index) {
      slot.querySelector("[data-slot-title]").textContent = `Slot ${index + 1}`;
      slot.querySelector("[data-remove-slot]").disabled = slots.length === 1;
      const isOnline = "ONLINE" === getSlotMode(slot);
      slot.querySelector("[data-slot-online]").hidden = !isOnline;
      slot.querySelector("[data-slot-location]").hidden = isOnline;
      slot.querySelector("[data-slot-platform]").required = isOnline;
      slot.querySelector("[data-slot-link]").required = isOnline;
      slot.querySelector("[data-slot-location-value]").required = !isOnline;
      slot.querySelectorAll("[data-slot-mode]").forEach(function updateMode(button) {
        const active = button.dataset.slotMode === getSlotMode(slot);
        button.classList.toggle("is-active", active);
        button.setAttribute("aria-pressed", String(active));
      });
    });
  };
  const showFeedback = function showFeedback(message, isError) {
    feedback.textContent = message;
    feedback.hidden = false;
    feedback.classList.toggle("is-error", Boolean(isError));
  };

  schedule.addEventListener("click", function handleScheduleClick(event) {
    const target = event.target.closest("button");
    if (!target || !schedule.contains(target)) {
      return;
    }

    if (target.matches("[data-schedule-date]")) {
      if (target.disabled) {
        return;
      }
      const date = target.dataset.scheduleDate;
      if (selectedDates.has(date)) {
        selectedDates.delete(date);
      } else {
        selectedDates.add(date);
      }
      feedback.hidden = true;
      updateSelection();
      return;
    }

    if (target.matches("[data-reset-selection]")) {
      selectedDates.clear();
      config.hidden = true;
      feedback.hidden = true;
      updateSelection();
      return;
    }

    if (target.matches("[data-open-config]")) {
      if (0 === selectedDates.size) {
        return;
      }
      config.hidden = false;
      feedback.hidden = true;
      updateSelection();
      updateSlotLabels();
      return;
    }

    if (target.matches("[data-close-config]")) {
      config.hidden = true;
      feedback.hidden = true;
      return;
    }

    if (target.matches("[data-slot-mode]")) {
      const slot = target.closest("[data-schedule-slot]");
      slot.querySelectorAll("[data-slot-mode]").forEach(function clearMode(button) {
        button.classList.toggle("is-active", button === target);
        button.setAttribute("aria-pressed", String(button === target));
      });
      updateSlotLabels();
      feedback.hidden = true;
      return;
    }

    if (target.matches("[data-add-slot]")) {
      const slot = slotTemplate.content.firstElementChild.cloneNode(true);
      const currentSlots = Array.from(slotList.querySelectorAll("[data-schedule-slot]"));
      if (currentSlots.length >= 20) {
        showFeedback("Maksimal 20 slot dapat dibuat sekaligus.", true);
        return;
      }
      const lastSlot = currentSlots[currentSlots.length - 1];
      if (lastSlot) {
        const previousEnd = lastSlot.querySelector("[data-slot-end]").value;
        if (previousEnd) {
          const [hour, minute] = previousEnd.split(":").map(Number);
          const nextStart = new Date(2000, 0, 1, hour, minute + 1);
          const nextEnd = new Date(2000, 0, 1, hour, minute + 31);
          const formatTime = function formatTime(date) {
            return `${String(date.getHours()).padStart(2, "0")}:${String(date.getMinutes()).padStart(2, "0")}`;
          };
          slot.querySelector("[data-slot-start]").value = formatTime(nextStart);
          slot.querySelector("[data-slot-end]").value = formatTime(nextEnd);
        }
      }
      slotList.append(slot);
      updateSlotLabels();
      slot.querySelector("[data-slot-start]").focus();
      feedback.hidden = true;
      return;
    }

    if (target.matches("[data-remove-slot]")) {
      const slots = slotList.querySelectorAll("[data-schedule-slot]");
      if (slots.length > 1) {
        target.closest("[data-schedule-slot]").remove();
        updateSlotLabels();
        feedback.hidden = true;
      }
      return;
    }

    if (target.matches("[data-save-schedule]")) {
      const slots = Array.from(slotList.querySelectorAll("[data-schedule-slot]"));
      const slotData = [];
      for (const [index, slot] of slots.entries()) {
        const start = slot.querySelector("[data-slot-start]");
        const end = slot.querySelector("[data-slot-end]");
        const capacity = slot.querySelector("[data-slot-capacity]");
        const mode = getSlotMode(slot);
        if (!start.value || !end.value || start.value >= end.value) {
          showFeedback(`Periksa waktu mulai dan selesai pada Slot ${index + 1}.`, true);
          end.focus();
          return;
        }
        const capacityValue = Number(capacity.value);
        if (!Number.isInteger(capacityValue) || capacityValue < 1 || capacityValue > 100) {
          showFeedback(`Kapasitas Slot ${index + 1} harus 1 sampai 100 orang.`, true);
          capacity.focus();
          return;
        }
        if ("ONLINE" === mode) {
          const platform = slot.querySelector("[data-slot-platform]");
          const link = slot.querySelector("[data-slot-link]");
          if (!platform.value.trim() || !link.value.trim() || !link.validity.valid) {
            showFeedback(`Lengkapi platform dan link online pada Slot ${index + 1}.`, true);
            (platform.value.trim() ? link : platform).focus();
            return;
          }
        } else {
          const location = slot.querySelector("[data-slot-location-value]");
          if (!location.value.trim()) {
            showFeedback(`Isi lokasi wawancara pada Slot ${index + 1}.`, true);
            location.focus();
            return;
          }
        }
        slotData.push({
          start: start.value,
          end: end.value,
          mode,
          capacity: capacityValue,
          platform: slot.querySelector("[data-slot-platform]").value.trim(),
          meeting_url: slot.querySelector("[data-slot-link]").value.trim(),
          location: slot.querySelector("[data-slot-location-value]").value.trim(),
          interviewer: slot.querySelector("[data-slot-interviewer]").value.trim(),
        });
      }

      const dates = sortedSelectedDates();
      for (const [index, first] of slotData.entries()) {
        for (const second of slotData.slice(index + 1)) {
          if (first.start < second.end && first.end > second.start) {
            showFeedback("Waktu slot dalam satu hari tidak boleh bertumpang tindih.", true);
            return;
          }
        }
      }

      datesInput.value = JSON.stringify(dates);
      slotsInput.value = JSON.stringify(slotData);
      target.disabled = true;
      if (typeof scheduleForm.requestSubmit === "function") {
        scheduleForm.requestSubmit();
      } else {
        scheduleForm.submit();
      }
    }
  });

  slotList.addEventListener("input", function clearFeedback() {
    feedback.hidden = true;
  });
  slotList.addEventListener("change", function clearFeedback() {
    feedback.hidden = true;
  });
  document.addEventListener("submit", function confirmScheduleDeletion(event) {
    const form = event.target.closest("[data-confirm]");
    if (form && !window.confirm(form.dataset.confirm)) {
      event.preventDefault();
    }
  });
  updateSelection();
  updateSlotLabels();
})();

(function vacancyPreview() {
  const screen = document.querySelector(".daw-vacancies");
  if (!screen) {
    return;
  }

  const list = screen.querySelector("[data-vacancy-list]");
  if (!list) {
    return;
  }

  const tabs = Array.from(screen.querySelectorAll("[data-vacancy-tab]"));
  const rows = Array.from(list.querySelectorAll("[data-vacancy-row]"));
  let activeStatus = "all";
  const applyStatusFilter = function applyStatusFilter() {
    rows.forEach(function filterRow(row) {
      row.hidden = "all" !== activeStatus && row.dataset.status !== activeStatus;
    });
  };

  tabs.forEach(function bindStatusTab(tab) {
    tab.addEventListener("click", function selectStatus() {
      activeStatus = tab.dataset.vacancyTab;
      tabs.forEach(function updateTab(item) {
        const selected = item === tab;
        item.classList.toggle("is-active", selected);
        item.setAttribute("aria-pressed", String(selected));
      });
      applyStatusFilter();
    });
  });
})();

(function recruitmentFormBuilder() {
  const builder = document.querySelector("[data-form-builder]");
  if (!builder) {
    return;
  }

  const sectionList = builder.querySelector("[data-form-sections]");
  const sectionTemplate = builder.parentElement.querySelector(
    "[data-section-template]",
  );
  const questionTemplate = builder.parentElement.querySelector(
    "[data-question-template]",
  );
  const typeLabels = {
    short: "Jawaban Singkat",
    paragraph: "Paragraf / Jawaban Panjang",
    number: "Angka",
    date: "Tanggal",
    email: "Email",
    phone: "Nomor Telepon",
    file: "Upload File",
    dropdown: "Pilihan Dropdown",
    single_choice: "Pilihan Satu Jawaban",
  };

  const refresh = function refreshBuilder() {
    const sections = Array.from(
      sectionList.querySelectorAll("[data-form-section]"),
    );
    let questionTotal = 0;
    sections.forEach(function updateSection(section, sectionIndex) {
      section.querySelector("[data-section-number]").textContent = String(
        sectionIndex + 1,
      ).padStart(2, "0");
      const title = section.querySelector("[data-section-title]");
      const description = section.querySelector("[data-section-description]");
      const sectionId = section.querySelector("[data-section-id]");
      sectionId.name = `form_sections[${sectionIndex}][section_id]`;
      title.name = `form_sections[${sectionIndex}][title]`;
      description.name = `form_sections[${sectionIndex}][description]`;
      const questions = Array.from(
        section.querySelectorAll("[data-form-question]"),
      );
      questionTotal += questions.length;
      section.querySelector("[data-section-question-count]").textContent =
        String(questions.length);
      questions.forEach(function updateQuestion(question, questionIndex) {
        question.querySelector("[data-question-number]").textContent = String(
          questionIndex + 1,
        );
        const fields = {
          field_id: question.querySelector("[data-question-id]"),
          key: question.querySelector("[data-question-key]"),
          title: question.querySelector("[data-question-title]"),
          type: question.querySelector("[data-question-type]"),
          options: question.querySelector("[data-question-options-value]"),
          required: question.querySelector("[data-question-required-input]"),
        };
        Object.keys(fields).forEach(function nameQuestionField(field) {
          if (fields[field]) {
            fields[field].name =
              `form_sections[${sectionIndex}][questions][${questionIndex}][${field}]`;
          }
        });
      });
    });
    builder.querySelector("[data-section-count]").textContent = String(
      sections.length,
    );
    builder.querySelector("[data-question-count]").textContent =
      String(questionTotal);
  };

  const updateQuestion = function updateQuestion(question) {
    const title = question.querySelector("[data-question-title]");
    const type = question.querySelector("[data-question-type]");
    const required = question.querySelector("[data-question-required-input]");
    question.querySelector("[data-question-label]").textContent =
      title.value.trim() || "Pertanyaan baru";
    question.querySelector("[data-question-type-label]").textContent =
      typeLabels[type.value] || type.value;
    question.querySelector("[data-question-required]").textContent =
      required.checked ? "Wajib diisi" : "Opsional";
    question.querySelector("[data-question-options]").hidden = ![
      "dropdown",
      "single_choice",
    ].includes(type.value);
  };

  const addQuestion = function addQuestion(section) {
    const question = questionTemplate.content.firstElementChild.cloneNode(true);
    question.querySelector("[data-question-key]").value =
      `field_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`;
    section.querySelector("[data-section-questions]").append(question);
    refresh();
    updateQuestion(question);
    question.querySelector("[data-question-title]").focus();
  };

  const moveItem = function moveItem(item, direction) {
    const sibling =
      "up" === direction
        ? item.previousElementSibling
        : item.nextElementSibling;
    if (!sibling) {
      return;
    }
    if ("up" === direction) {
      item.parentElement.insertBefore(item, sibling);
    } else {
      item.parentElement.insertBefore(sibling, item);
    }
    refresh();
  };

  builder.addEventListener("input", function updateQuestionOnInput(event) {
    const question = event.target.closest("[data-form-question]");
    if (question && event.target.matches("[data-question-title]")) {
      updateQuestion(question);
    }
  });
  builder.addEventListener("change", function updateQuestionOnChange(event) {
    const question = event.target.closest("[data-form-question]");
    if (
      question &&
      event.target.matches(
        "[data-question-type], [data-question-required-input]",
      )
    ) {
      updateQuestion(question);
    }
  });

  builder.addEventListener("click", function handleBuilderClick(event) {
    const target = event.target.closest("button");
    if (!target) {
      return;
    }
    if (target.matches("[data-add-section]")) {
      const section = sectionTemplate.content.firstElementChild.cloneNode(true);
      sectionList.append(section);
      refresh();
      section.querySelector("[data-section-title]").focus();
    } else if (target.matches("[data-add-question]")) {
      addQuestion(target.closest("[data-form-section]"));
    } else if (target.matches("[data-remove-question]")) {
      target.closest("[data-form-question]").remove();
      refresh();
    } else if (target.matches("[data-remove-section]")) {
      if (
        sectionList.querySelectorAll("[data-form-section]").length > 1 &&
        window.confirm("Hapus section beserta seluruh pertanyaannya?")
      ) {
        target.closest("[data-form-section]").remove();
        refresh();
      }
    } else if (target.matches("[data-move-section]")) {
      moveItem(
        target.closest("[data-form-section]"),
        target.dataset.moveSection,
      );
    } else if (target.matches("[data-move-question]")) {
      moveItem(
        target.closest("[data-form-question]"),
        target.dataset.moveQuestion,
      );
    }
  });

  const preview = builder.parentElement.querySelector("[data-form-preview]");
  const previewContent = preview.querySelector("[data-preview-content]");
  builder.parentElement
    .querySelector("[data-preview-form]")
    .addEventListener("click", function openPreview() {
      previewContent.replaceChildren();
      sectionList
        .querySelectorAll("[data-form-section]")
        .forEach(function renderPreviewSection(section) {
          const previewSection = document.createElement("section");
          const heading = document.createElement("h3");
          heading.textContent = section.querySelector(
            "[data-section-title]",
          ).value;
          previewSection.append(heading);
          const description = section.querySelector(
            "[data-section-description]",
          ).value;
          if (description) {
            const text = document.createElement("p");
            text.textContent = description;
            previewSection.append(text);
          }
          section
            .querySelectorAll("[data-form-question]")
            .forEach(function renderPreviewQuestion(question) {
              const label = document.createElement("label");
              label.className = "recruitment-form-preview__field";
              const title = document.createElement("span");
              title.textContent = question.querySelector(
                "[data-question-title]",
              ).value;
              if (
                question.querySelector("[data-question-required-input]").checked
              ) {
                title.append(document.createTextNode(" *"));
              }
              label.append(title);
              const type = question.querySelector("[data-question-type]").value;
              let input;
              if ("paragraph" === type) {
                input = document.createElement("textarea");
              } else if (["dropdown", "single_choice"].includes(type)) {
                input = document.createElement("select");
                question
                  .querySelector("[data-question-options-value]")
                  .value.split(/\r?\n/)
                  .filter(Boolean)
                  .forEach(function addPreviewOption(optionText) {
                    const option = document.createElement("option");
                    option.textContent = optionText;
                    input.append(option);
                  });
              } else {
                input = document.createElement("input");
                input.type =
                  "file" === type
                    ? "file"
                    : ["short", "number", "date", "email", "phone"].includes(
                          type,
                        )
                      ? { phone: "tel" }[type] || type
                      : "text";
              }
              input.disabled = true;
              label.append(input);
              previewSection.append(label);
            });
          previewContent.append(previewSection);
        });
      preview.showModal();
    });
  preview
    .querySelector("[data-close-preview]")
    .addEventListener("click", function closePreview() {
      preview.close();
    });
  preview.addEventListener("click", function closePreviewOnBackdrop(event) {
    if (event.target === preview) {
      preview.close();
    }
  });

  sectionList.querySelectorAll("[data-form-question]").forEach(updateQuestion);
  refresh();
})();

(function recruitmentSidebarCollapse() {
  const sidebar = document.querySelector("[data-recruitment-sidebar]");
  if (!sidebar) {
    return;
  }

  const collapseButton = sidebar.querySelector("[data-sidebar-collapse]");
  const isMobile = window.matchMedia("(max-width: 782px)").matches;
  document.body.classList.toggle("daw-recruitment-sidebar-collapsed", isMobile);

  const updateButton = function updateButton() {
    const isExpanded = !document.body.classList.contains(
      "daw-recruitment-sidebar-collapsed",
    );
    collapseButton.setAttribute("aria-expanded", String(isExpanded));
    collapseButton.title = isExpanded ? "Ciutkan navigasi" : "Buka navigasi";
  };

  updateButton();
  collapseButton.addEventListener("click", function toggleSidebar() {
    document.body.classList.toggle("daw-recruitment-sidebar-collapsed");
    updateButton();
  });

  sidebar
    .querySelectorAll("[data-nav-group-toggle]")
    .forEach(function bindNavGroup(toggle) {
      const panel = document.getElementById(
        toggle.getAttribute("aria-controls"),
      );
      if (!panel) {
        return;
      }

      toggle.addEventListener("click", function toggleNavGroup() {
        const isExpanded = "true" === toggle.getAttribute("aria-expanded");
        toggle.setAttribute("aria-expanded", String(!isExpanded));
        panel.hidden = isExpanded;
      });
    });
})();

(function psychotestQuestionBank() {
  const bank = document.querySelector("[data-question-bank]");
  if (!bank) {
    return;
  }

  const pdfTest = bank.querySelector("[data-pdf-import-test]");
  const pdfSubtest = bank.querySelector("[data-pdf-import-subtest]");
  const pdfSubtestField = bank.querySelector("[data-pdf-import-subtest-field]");
  if (pdfTest && pdfSubtest && pdfSubtestField) {
    const updatePdfSubtest = function updatePdfSubtest() {
      const isIq = "iq" === pdfTest.value;
      pdfSubtestField.hidden = !isIq;
      pdfSubtest.required = isIq;
    };
    pdfTest.addEventListener("change", updatePdfSubtest);
    updatePdfSubtest();
  }

  const dialog = bank.querySelector("[data-question-dialog]");
  const form = bank.querySelector("[data-question-form]");
  const prompt = form.querySelector("[data-question-prompt]");
  const choices = form.querySelector("[data-question-choices]");
  const choicesLabel = form.querySelector("[data-question-choices-label]");
  const answerLabel = form.querySelector("[data-question-answer-label]");
  const test = form.querySelector("[data-question-test]");
  const subtest = form.querySelector("[data-question-subtest]");
  const type = form.querySelector("[data-question-type]");
  const promptHelp = form.querySelector("[data-question-prompt-help]");
  const questionAction = form.querySelector("[data-question-action]");
  const questionTestValue = form.querySelector("[data-question-test-value]");
  const questionSubtestValue = form.querySelector(
    "[data-question-subtest-value]",
  );
  const questionTypeValue = form.querySelector("[data-question-type-value]");
  const updateQuestionFields = function updateQuestionFields() {
    const isIq = "iq" === test.value;
    const isPauli = "pauli" === test.value;
    const isPersonality = ["disc", "papi"].includes(test.value);
    form.querySelector("[data-question-subtest-label]").hidden = !isIq;
    form.querySelector("[data-question-type-label]").hidden = !isIq;
    choicesLabel.hidden = !isIq;
    answerLabel.hidden = !isIq;
    subtest.disabled = !isIq || Boolean(form.querySelector("[data-question-id]").value);
    type.disabled = !isIq || Boolean(form.querySelector("[data-question-id]").value);
    questionTestValue.value = test.value;
    questionSubtestValue.value = subtest.value;
    questionTypeValue.value = type.value;
    if (isIq) {
      promptHelp.textContent = "Masukkan satu pertanyaan IQ.";
    } else if (isPauli) {
      promptHelp.textContent =
        "Setiap entri bank menjadi satu lajur Pauli; lajur berjalan tiga menit dan memuat hingga 900 jawaban.";
    } else if (isPersonality) {
      promptHelp.textContent =
        "Masukkan satu pernyataan per baris (DISC: 4; PAPI Kostick: 2).";
    } else {
      promptHelp.textContent = "";
    }
  };
  const setQuestionTypeForTest = function setQuestionTypeForTest() {
    if ("iq" === test.value) {
      type.value = "multiple_choice";
    } else if ("pauli" === test.value) {
      type.value = "numeric";
    } else {
      type.value = "paired";
    }
    updateQuestionFields();
  };
  const updateTypeFields = function updateTypeFields() {
    const isIq = "iq" === test.value;
    const questionType = type.value;
    choicesLabel.hidden = !isIq || "numeric" === questionType;
    answerLabel.hidden =
      !isIq ||
      !["multiple_choice", "multi_select", "numeric"].includes(questionType);
    questionTypeValue.value = type.value;
  };
  const openDialog = function openDialog(question) {
    form.reset();
    form.querySelector("[data-question-id]").value = question
      ? question.dataset.id
      : "";
    questionAction.value = question ? "save_question" : "create_question";
    test.value = question
      ? question.dataset.test
      : bank.dataset.filterTest || "iq";
    subtest.value = question ? question.dataset.subtest || "" : "iq_numbers";
    type.value = question
      ? question.dataset.type
      : "iq" === test.value
        ? "multiple_choice"
        : "pauli" === test.value
          ? "numeric"
          : "paired";
    test.disabled = Boolean(question);
    prompt.value = question
      ? question.dataset.prompt || ""
      : "pauli" === test.value
        ? "Kolom Pauli baru"
        : "";
    choices.value = question ? question.dataset.choices || "" : "";
    form.querySelector("[data-question-answer]").value = question
      ? question.dataset.answer || ""
      : "";
    form.querySelector("[data-question-submit]").textContent = question
      ? "Simpan Perubahan"
      : "Tambah Soal";
    bank.querySelector("#question-dialog-title").textContent = question
      ? "Edit Soal"
      : "Tambah Soal";
    updateQuestionFields();
    updateTypeFields();
    dialog.showModal();
    prompt.focus();
  };

  bank
    .querySelector("[data-new-question]")
    .addEventListener("click", function addQuestion() {
      openDialog(null);
    });
  bank
    .querySelectorAll("[data-edit-question]")
    .forEach(function bindEditButton(button) {
      button.addEventListener("click", function editQuestion() {
        openDialog(button);
      });
    });
  form
    .querySelector("[data-question-type]")
    .addEventListener("change", updateTypeFields);
  test.addEventListener("change", setQuestionTypeForTest);
  subtest.addEventListener("change", function syncQuestionSubtest() {
    questionSubtestValue.value = subtest.value;
  });
  dialog
    .querySelectorAll("[data-close-question-dialog]")
    .forEach(function bindClose(button) {
      button.addEventListener("click", function closeDialog() {
        dialog.close();
      });
    });
  dialog.addEventListener("click", function closeOnBackdrop(event) {
    if (event.target === dialog) {
      dialog.close();
    }
  });
  updateTypeFields();
})();

(function userDepartmentInterviewScoring() {
  const form = document.querySelector("[data-interview-rating]");
  if (!form) {
    return;
  }

  const total = form.querySelector("[data-score-total]");
  const ratingCount = form.querySelectorAll(".daw-ud-rating-row").length;
  const scoreInputs = Array.from(
    form.querySelectorAll('input[type="radio"][name^="scores["]'),
  );
  const updateTotal = function updateTotal() {
    const selectedScores = scoreInputs.filter(function isSelected(input) {
      return input.checked;
    });
    const score = selectedScores.reduce(function sumScores(sum, input) {
      return sum + Number(input.value);
    }, 0);
    total.textContent = String(Math.floor((score / (ratingCount * 5)) * 100));
  };

  form.addEventListener("change", function updateInterviewSelection(event) {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || "radio" !== input.type) {
      return;
    }
    const group = input.closest("fieldset");
    if (group) {
      group
        .querySelectorAll("label")
        .forEach(function updateSelectedLabel(label) {
          const radio = label.querySelector('input[type="radio"]');
          label.classList.toggle(
            "is-selected",
            Boolean(radio && radio.checked),
          );
        });
    }
    updateTotal();
  });

  updateTotal();
})();

(function recruitmentDepartmentAccounts() {
  const screen = document.querySelector(".daw-account-settings");
  if (!screen) {
    return;
  }

  const dialog = screen.querySelector("[data-account-dialog]");
  if (dialog && dialog.hasAttribute("data-oracle-account-dialog")) {
    const accountAction = dialog.querySelector("[data-account-action]");
    const accountNonce = dialog.querySelector("[data-account-nonce]");
    const accountTypes = dialog.querySelectorAll("[data-account-type]");
    const accountForms = dialog.querySelectorAll("[data-account-form]");
    const displayNameFields = dialog.querySelectorAll('[name="display_name"]');
    const generatedUsername = dialog.querySelector("[data-generated-username]");
    const setAccountType = function setAccountType(type) {
      const isDepartment = "department" === type;
      accountAction.value = isDepartment
        ? "daw_create_department_account"
        : "daw_create_hr_account";
      accountNonce.value = isDepartment
        ? accountNonce.dataset.departmentNonce
        : accountNonce.dataset.hrNonce;
      accountForms.forEach(function toggleAccountForm(accountForm) {
        const active = accountForm.dataset.accountForm === type;
        accountForm.hidden = !active;
        accountForm.querySelectorAll("input, select").forEach(function toggleField(field) {
          field.disabled = !active;
        });
      });
      dialog.querySelectorAll("[data-account-type]").forEach(function updateRoleStyle(role) {
      role.parentElement.classList.toggle("is-selected", role.checked);
      });
    };
    displayNameFields.forEach(function bindDisplayName(field) {
      field.addEventListener("input", function generateUsername() {
      if (generatedUsername && !generatedUsername.disabled) {
        generatedUsername.value = field.value.toLowerCase().trim().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "").slice(0, 100);
      }
      });
    });
    accountNonce.dataset.hrNonce = accountNonce.value;
    accountNonce.dataset.departmentNonce = accountNonce.dataset.departmentNonce || "";
    accountTypes.forEach(function bindAccountType(type) {
      type.addEventListener("change", function changeAccountType() {
        setAccountType(type.value);
      });
    });
    const openOracleDialog = function openOracleDialog() {
      const selectedType = dialog.querySelector("[data-account-type]:checked");
      setAccountType(selectedType ? selectedType.value : "department");
      if (!dialog.open) {
        dialog.showModal();
      }
    };
    document.addEventListener("click", function delegatedOracleAccountOpen(event) {
      const trigger = event.target.closest("[data-open-account-dialog]");
      if (trigger && screen.contains(trigger)) {
        openOracleDialog();
      }
    });
    screen.querySelectorAll("[data-close-account-dialog]").forEach(function bindOracleClose(button) {
      button.addEventListener("click", function closeOracleDialog() {
        dialog.close();
      });
    });
    const editDialog = screen.querySelector("[data-edit-oracle-dialog]");
    if (editDialog) {
      screen.querySelectorAll("[data-edit-oracle-account]").forEach(function bindOracleEdit(button) {
        button.addEventListener("click", function openOracleEdit() {
          editDialog.querySelector("[data-edit-account-id]").value = button.dataset.accountId;
          editDialog.querySelector("[data-edit-account-username]").value = button.dataset.accountUsername;
          editDialog.querySelector("[data-edit-account-full-name]").value = button.dataset.accountFullName || button.dataset.accountUsername;
          editDialog.querySelector("[data-edit-account-email]").value = button.dataset.accountEmail;
          const department = editDialog.querySelector("[data-edit-account-department]");
          const option = Array.from(department.options).find(function findDepartment(item) {
            return item.textContent.trim() === button.dataset.accountDepartment;
          });
          if (option) {
            department.value = option.value;
          }
          editDialog.showModal();
        });
      });
      editDialog.querySelectorAll("[data-close-edit-account]").forEach(function bindEditClose(button) {
        button.addEventListener("click", function closeOracleEdit() {
          editDialog.close();
        });
      });
    }
    return;
  }
  const form = screen.querySelector("[data-account-form]");
  if (!dialog || !form) {
    return;
  }
  const title = screen.querySelector("[data-account-dialog-title]");
  const userId = form.querySelector("[data-account-id]");
  const username = form.querySelector("[data-account-login]");
  const displayName = form.querySelector("[data-account-name]");
  const email = form.querySelector("[data-account-email]");
  const department = form.querySelector("[data-account-department]");
  const password = form.querySelector("[data-account-password]");
  const passwordConfirmation = form.querySelector(
    "[data-account-password-confirm]",
  );
  const passwordHelp = form.querySelector("[data-password-help]");
  const accountType = form.querySelector("[data-account-type]");
  const accountTypeField = form.querySelector("[data-account-type-field]");
  const departmentField = form.querySelector("[data-account-department-field]");

  const updateAccountFields = function updateAccountFields(kind, isEditing) {
    const isApplicant = "applicant" === kind;
    accountType.value = isApplicant ? "applicant" : "department";
    accountTypeField.hidden = isEditing;
    departmentField.hidden = isApplicant;
    department.disabled = isApplicant;
    department.required = !isApplicant;
    if (isApplicant) {
      department.value = "";
    }
    password.minLength = isApplicant ? 10 : 8;
    passwordConfirmation.minLength = password.minLength;
    passwordHelp.textContent = isEditing
      ? "Kosongkan jika tidak ingin mengganti password."
      : isApplicant
        ? "Wajib diisi, minimal 10 karakter."
        : "Wajib diisi, minimal 8 karakter.";
  };

  accountType.addEventListener("change", function changeAccountType() {
    updateAccountFields(accountType.value, false);
  });

  const openDialog = function openDialog(account) {
    form.reset();
    userId.value = account ? account.dataset.accountId : "";
    username.value = account ? account.dataset.accountLogin : "";
    username.readOnly = Boolean(account);
    displayName.value = account ? account.dataset.accountName : "";
    email.value = account ? account.dataset.accountEmail : "";
    department.value = account ? account.dataset.accountDepartment : "";
    password.required = !account;
    passwordConfirmation.required = !account;
    updateAccountFields(account ? account.dataset.accountKind : accountType.value, Boolean(account));
    title.textContent = account ? "Edit Akun" : "Tambah Akun";
    dialog.showModal();
    displayName.focus();
  };

  screen
    .querySelector("[data-open-account-dialog]")
    .addEventListener("click", function addAccount() {
      openDialog(null);
    });

  screen.querySelectorAll("[data-edit-account]").forEach(function bindEdit(button) {
    button.addEventListener("click", function editAccount() {
      openDialog(button);
    });
  });

  screen
    .querySelectorAll("[data-close-account-dialog]")
    .forEach(function bindClose(button) {
      button.addEventListener("click", function closeDialog() {
        dialog.close();
      });
    });

  form.addEventListener("submit", function validatePasswordConfirmation(event) {
    passwordConfirmation.setCustomValidity(
      password.value === passwordConfirmation.value
        ? ""
        : "Konfirmasi password tidak cocok.",
    );
    if (!form.reportValidity()) {
      event.preventDefault();
    }
  });
  passwordConfirmation.addEventListener("input", function clearPasswordError() {
    passwordConfirmation.setCustomValidity("");
  });

  dialog.addEventListener("click", function closeOnBackdrop(event) {
    if (event.target === dialog) {
      dialog.close();
    }
  });
})();
