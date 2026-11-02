// admin.js — JavaScript for the Recruitment Plugin HRD admin dashboard.
// TODO: Tambahkan interaksi admin seperti konfirmasi delete, filter tabel, dll.
document.documentElement.classList.add("js");

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

(function userInterviewNotes() {
  const screen = document.querySelector(".daw-user-interview--notes");
  if (!screen) {
    return;
  }

  screen
    .querySelectorAll("[data-choice-group]")
    .forEach(function bindChoiceGroup(group) {
      group.querySelectorAll("button").forEach(function bindChoice(button) {
        button.addEventListener("click", function selectChoice() {
          group
            .querySelectorAll("button")
            .forEach(function clearChoice(choice) {
              const selected = choice === button;
              choice.classList.toggle("is-selected", selected);
              choice.setAttribute("aria-pressed", String(selected));
            });
        });
      });
    });

  const form = screen.querySelector("[data-user-interview-form]");
  const saveFeedback = screen.querySelector("[data-interview-feedback]");
  form.addEventListener("submit", function saveInterviewNotes(event) {
    event.preventDefault();
    saveFeedback.textContent = "Catatan diperbarui pada preview ini.";
    saveFeedback.hidden = false;
  });

  const decisionFeedback = screen.querySelector("[data-decision-feedback]");
  screen
    .querySelectorAll("[data-final-decision]")
    .forEach(function bindDecision(button) {
      button.addEventListener("click", function chooseDecision() {
        screen
          .querySelectorAll("[data-final-decision]")
          .forEach(function updateDecision(choice) {
            const selected = choice === button;
            choice.classList.toggle("is-selected", selected);
            choice.setAttribute("aria-pressed", String(selected));
          });
        decisionFeedback.textContent =
          "Keputusan dipilih: " +
          button.textContent.trim().replace(/^[✓×]\s*/, "");
        decisionFeedback.hidden = false;
      });
    });
})();

(function recruitmentInterviewSchedule() {
  const schedule = document.querySelector(".daw-interview-schedule");
  if (!schedule) {
    return;
  }

  const dates = Array.from(schedule.querySelectorAll("[data-schedule-date]"));
  const entries = Array.from(schedule.querySelectorAll(".daw-schedule__entry"));

  dates.forEach(function bindScheduleDate(button) {
    button.addEventListener("click", function selectScheduleDate() {
      dates.forEach(function updateDate(item) {
        const selected = item === button;
        item.classList.toggle("is-selected", selected);
        item.setAttribute("aria-pressed", String(selected));
      });

      entries.forEach(function showSelectedEntry(entry) {
        entry.open = entry.id === "schedule-day-" + button.dataset.scheduleDate;
      });
    });
  });
})();

(function vacancyPreview() {
  const screen = document.querySelector(".daw-vacancies");
  if (!screen) {
    return;
  }

  const createForm = screen.querySelector("[data-vacancy-create-form]");
  const list = screen.querySelector("[data-vacancy-list]");
  const feedback = screen.querySelector("[data-vacancy-feedback]");
  const getStatusLabel = function getStatusLabel(status) {
    return {
      published: "Published",
      draft: "Draft",
      closed: "Closed",
      archived: "Archived",
    }[status];
  };
  const setStatus = function setStatus(row, status) {
    row.dataset.status = status;
    const badge = row.querySelector("[data-vacancy-status]");
    badge.className = "daw-vacancies__badge daw-vacancies__badge--" + status;
    badge.textContent = getStatusLabel(status);
    const actionButton = row.querySelector("[data-vacancy-toggle]");
    if (actionButton) {
      actionButton.textContent = "published" === status ? "Tutup" : "Publish";
    }
  };
  const showFeedback = function showFeedback(message) {
    if (feedback) {
      feedback.textContent = message;
      feedback.hidden = false;
    }
  };

  if (createForm) {
    createForm.addEventListener("submit", function previewVacancy(event) {
      event.preventDefault();
      showFeedback(
        "Validasi berhasil. Penyimpanan lowongan belum dihubungkan ke database.",
      );
    });

    const saveDraftButton = createForm.querySelector("[data-vacancy-save-draft]");
    saveDraftButton.addEventListener("click", function saveDraft() {
      createForm.querySelector('[name="status"][value="draft"]').checked = true;
      createForm.requestSubmit();
    });
  }

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

  list.addEventListener("click", function handleVacancyAction(event) {
    const button = event.target.closest("button");
    const row = button && button.closest("[data-vacancy-row]");
    if (!button || !row) {
      return;
    }
    if (button.hasAttribute("data-vacancy-toggle")) {
      const status = "published" === row.dataset.status ? "closed" : "published";
      setStatus(row, status);
      showFeedback("Status lowongan diperbarui pada pratinjau.");
    } else if (button.hasAttribute("data-vacancy-archive")) {
      setStatus(row, "archived");
      button.remove();
      showFeedback("Lowongan diarsipkan pada pratinjau.");
    }
    applyStatusFilter();
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

  const dialog = bank.querySelector("[data-question-dialog]");
  const form = bank.querySelector("[data-question-form]");
  const prompt = form.querySelector("[data-question-prompt]");
  const choices = form.querySelector("[data-question-choices]");
  const choicesLabel = form.querySelector("[data-question-choices-label]");
  const answerLabel = form.querySelector("[data-question-answer-label]");
  const updateTypeFields = function updateTypeFields() {
    const type = form.querySelector("[data-question-type]").value;
    choicesLabel.hidden = "numeric" === type;
    answerLabel.hidden = !["multiple_choice", "numeric"].includes(type);
  };
  const openDialog = function openDialog(question) {
    form.reset();
    form.querySelector("[data-question-id]").value = question
      ? question.dataset.id
      : "";
    form.querySelector("[data-question-test]").value = question
      ? question.dataset.test
      : "iq";
    form.querySelector("[data-question-type]").value = question
      ? question.dataset.type
      : "multiple_choice";
    form.querySelector("[data-question-status]").value = question
      ? question.dataset.status
      : "active";
    prompt.value = question ? question.dataset.prompt : "";
    choices.value = question ? question.dataset.choices : "";
    form.querySelector("[data-question-answer]").value = question
      ? question.dataset.answer
      : "";
    bank.querySelector("#question-dialog-title").textContent = question
      ? "Edit Soal"
      : "Tambah Soal";
    updateTypeFields();
    dialog.showModal();
    prompt.focus();
  };

  bank
    .querySelector("[data-open-question-dialog]")
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

(function userDepartmentProfilePrint() {
  const profile = document.querySelector("[data-candidate-profile]");
  if (!profile) {
    return;
  }

  const printProfile = function printProfile() {
    const previousTitle = document.title;
    document.title = "Profil-Pelamar-" + profile.dataset.candidateCode;
    window.addEventListener(
      "afterprint",
      function restoreTitle() {
        document.title = previousTitle;
      },
      { once: true },
    );
    window.print();
  };

  profile
    .querySelector("[data-print-profile]")
    .addEventListener("click", printProfile);

  if ("1" === new URLSearchParams(window.location.search).get("print")) {
    if ("complete" === document.readyState) {
      window.requestAnimationFrame(printProfile);
    } else {
      window.addEventListener("load", printProfile, { once: true });
    }
  }
})();

(function recruitmentDepartmentAccounts() {
  const screen = document.querySelector(".daw-account-settings");
  if (!screen) {
    return;
  }

  const dialog = screen.querySelector("[data-account-dialog]");
  const form = screen.querySelector("[data-account-form]");
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
