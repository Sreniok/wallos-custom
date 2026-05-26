function quickAddSubscription() {
  closeQuickAddMenu();
  if (typeof addSubscription === "function") {
    addSubscription();
    return;
  }
  window.location.href = "subscriptions.php?add=1";
}

function expenseLabel(key, fallback) {
  const value = translate(key);
  return value === key ? fallback : value;
}

function copyTextToClipboard(text) {
  if (navigator.clipboard && window.isSecureContext) {
    return navigator.clipboard.writeText(text);
  }

  const textarea = document.createElement("textarea");
  textarea.value = text;
  textarea.setAttribute("readonly", "");
  textarea.style.position = "fixed";
  textarea.style.opacity = "0";
  document.body.appendChild(textarea);
  textarea.select();

  return new Promise((resolve, reject) => {
    try {
      document.execCommand("copy") ? resolve() : reject(new Error("Copy failed"));
    } catch (error) {
      reject(error);
    } finally {
      textarea.remove();
    }
  });
}

function copyFuelVehicleNfcLink(target) {
  const url = typeof target === "string" ? target : target?.dataset?.nfcUrl;
  if (!url) {
    showErrorMessage(translate("unknown_error"));
    return;
  }

  copyTextToClipboard(url)
    .then(() => showSuccessMessage(translate("copied_to_clipboard")))
    .catch(error => {
      console.error(error);
      showErrorMessage(url);
    });
}

function openPetrolExpenseModalFromUrl() {
  const params = new URLSearchParams(window.location.search);
  if (params.get("add_petrol") !== "1") {
    return;
  }

  const vehicleId = params.get("vehicle_id");
  openPetrolExpenseModal(vehicleId);
  params.delete("add_petrol");
  params.delete("vehicle_id");
  const nextUrl = `${window.location.pathname}${params.toString() ? `?${params.toString()}` : ""}${window.location.hash}`;
  window.history.replaceState({}, "", nextUrl);
}

function fuelVehicleSearchTerm() {
  const name = document.getElementById("fuelVehicleName")?.value.trim() || "";
  const make = document.getElementById("fuelVehicleMake")?.value.trim() || "";
  const model = document.getElementById("fuelVehicleModel")?.value.trim() || "";
  return name || `${make} ${model}`.trim();
}

function setFuelVehicleLogoSearchStatus() {
  const button = document.getElementById("fuelVehicleLogoSearchButton");
  if (!button) {
    return;
  }

  button.classList.toggle("disabled", fuelVehicleSearchTerm().length === 0);
}

function searchFuelVehicleLogo() {
  const searchTerm = fuelVehicleSearchTerm();
  if (!searchTerm) {
    document.getElementById("fuelVehicleMake")?.focus();
    return;
  }

  const popup = document.getElementById("fuelVehicleLogoSearchResults");
  const images = document.getElementById("fuelVehicleLogoSearchImages");
  if (!popup || !images) {
    return;
  }

  popup.classList.add("is-open");
  images.innerHTML = "";

  apiFetch(`endpoints/logos/search.php?search=${encodeURIComponent(searchTerm)}`)
    .then(data => {
      if (!data.results) {
        return;
      }

      data.results.forEach(src => {
        const img = document.createElement("img");
        img.src = src.thumbnail || src.image;
        img.addEventListener("click", function() {
          selectFuelVehicleLogo(src.thumbnail || src.image);
        });
        img.addEventListener("error", function() {
          this.remove();
        });
        images.appendChild(img);
      });
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(translate("unknown_error"));
    });
}

function selectFuelVehicleLogo(url) {
  const preview = document.getElementById("fuelVehicleLogoPreview");
  const logoUrl = document.getElementById("fuelVehicleLogoUrl");
  if (preview) {
    preview.src = url;
    preview.style.display = "block";
  }
  if (logoUrl) {
    logoUrl.value = url;
  }
  closeFuelVehicleLogoSearch();
}

function closeFuelVehicleLogoSearch() {
  const popup = document.getElementById("fuelVehicleLogoSearchResults");
  const images = document.getElementById("fuelVehicleLogoSearchImages");
  popup?.classList.remove("is-open");
  if (images) {
    images.innerHTML = "";
  }
}

function positionQuickAddMenu(anchor) {
  const menu = document.getElementById("quickAddMenu");
  if (!menu || !anchor) {
    return;
  }

  const rect = anchor.getBoundingClientRect();
  menu.hidden = false;
  menu.classList.add("is-open");

  const menuRect = menu.getBoundingClientRect();
  const top = rect.top > menuRect.height + 24
    ? Math.max(12, rect.top - menuRect.height - 10)
    : Math.min(window.innerHeight - menuRect.height - 12, rect.bottom + 10);
  const left = Math.min(
    window.innerWidth - menuRect.width - 12,
    Math.max(12, rect.left + (rect.width / 2) - (menuRect.width / 2))
  );

  menu.style.top = `${top}px`;
  menu.style.left = `${left}px`;
}

function toggleQuickAddMenu(anchorOrEvent) {
  const menu = document.getElementById("quickAddMenu");
  if (!menu) {
    quickAddSubscription();
    return;
  }

  const anchor = anchorOrEvent && anchorOrEvent.currentTarget
    ? anchorOrEvent.currentTarget
    : anchorOrEvent;

  if (menu.classList.contains("is-open")) {
    closeQuickAddMenu();
    return;
  }

  positionQuickAddMenu(anchor);
}

function closeQuickAddMenu() {
  const menu = document.getElementById("quickAddMenu");
  if (!menu) {
    return;
  }

  menu.classList.remove("is-open");
  menu.hidden = true;
}

function openPetrolExpenseModal(vehicleId) {
  closeQuickAddMenu();
  const modal = document.getElementById("petrolExpenseModal");
  if (!modal) {
    return;
  }

  const vehicleSelect = document.getElementById("petrolVehicle");
  if (vehicleSelect && vehicleId) {
    vehicleSelect.value = String(vehicleId);
  }

  modal.classList.add("is-open");
  document.body.classList.add("no-scroll", "fab-hidden");
  setTimeout(() => document.getElementById("petrolAmount")?.focus(), 0);
  updatePetrolUnitPrice();
}

function closePetrolExpenseModal() {
  const modal = document.getElementById("petrolExpenseModal");
  if (!modal) {
    return;
  }

  modal.classList.remove("is-open");
  document.body.classList.remove("no-scroll", "fab-hidden");
}

function openFuelVehicleForm(reset = true) {
  const modal = document.getElementById("fuelVehicleForm");
  if (!modal) {
    return;
  }

  if (reset) {
    document.getElementById("fuelVehicleCreateForm")?.reset();
    const idField = document.getElementById("fuelVehicleId");
    if (idField) {
      idField.value = "";
    }
    const logoUrl = document.getElementById("fuelVehicleLogoUrl");
    const preview = document.getElementById("fuelVehicleLogoPreview");
    if (logoUrl) {
      logoUrl.value = "";
    }
    if (preview) {
      preview.src = "";
      preview.style.display = "none";
    }
    closeFuelVehicleLogoSearch();
    setFuelVehicleLogoSearchStatus();
  }

  modal.classList.add("is-open");
  document.body.classList.add("no-scroll", "fab-hidden");
  setTimeout(() => document.getElementById("fuelVehicleName")?.focus(), 0);
}

function closeFuelVehicleForm() {
  const modal = document.getElementById("fuelVehicleForm");
  if (!modal) {
    return;
  }

  modal.classList.remove("is-open");
  document.body.classList.remove("no-scroll", "fab-hidden");
}

let currentFuelHistoryVehicleId = null;
let currentFuelHistoryOffset = 0;

function closeFuelVehicleHistory() {
  const modal = document.getElementById("fuelVehicleHistoryModal");
  if (!modal) {
    return;
  }

  modal.classList.remove("is-open");
  document.body.classList.remove("no-scroll", "fab-hidden");
}

function changeFuelHistoryPeriod(step) {
  if (!currentFuelHistoryVehicleId) {
    return;
  }

  currentFuelHistoryOffset += step;
  openFuelVehicleHistory(currentFuelHistoryVehicleId, currentFuelHistoryOffset);
}

function openFuelVehicleHistory(vehicleId, offset = 0) {
  const modal = document.getElementById("fuelVehicleHistoryModal");
  const summary = document.getElementById("fuelVehicleHistorySummary");
  const list = document.getElementById("fuelVehicleHistoryList");
  if (!modal || !summary || !list || !vehicleId) {
    return;
  }

  const formData = new FormData();
  formData.append("csrf_token", window.csrfToken);
  formData.append("vehicle_id", vehicleId);
  formData.append("offset", offset);

  currentFuelHistoryVehicleId = vehicleId;
  currentFuelHistoryOffset = offset;

  summary.textContent = translate("loading");
  list.innerHTML = "";
  modal.classList.add("is-open");
  document.body.classList.add("no-scroll", "fab-hidden");

  safeFetch("endpoints/fuel_vehicles/history.php", {
    method: "POST",
    headers: {
      "X-CSRF-Token": window.csrfToken,
    },
    body: formData,
  })
    .then(response => response.json())
    .then(data => {
      if (!data.success) {
        showErrorMessage(data.message || translate("unknown_error"));
        closeFuelVehicleHistory();
        return;
      }

      document.getElementById("fuelVehicleHistoryTitle").textContent = data.vehicle.name;
      summary.innerHTML = `
        <span class="fuel-history-period">${data.period.label}: ${data.period.start} - ${data.period.end}</span>
        <strong>${data.summary.total}</strong>
        <span>${data.summary.fills} ${translate("fills")}</span>
      `;

      if (!data.rows.length) {
        list.innerHTML = `<p class="empty-history">${translate("no_fuel_entries")}</p>`;
        return;
      }

      list.innerHTML = data.rows.map(row => `
        <div class="fuel-history-row" data-expense-id="${row.id}">
          <div>
            <strong>${row.date}</strong>
            <span>${row.quantity ? `${row.quantity} ${row.unit}` : ""}</span>
          </div>
          <div>
            <strong>${row.amount}</strong>
            <span>${row.unit_price ? `${row.unit_price} / ${row.unit}` : ""}</span>
          </div>
          <button type="button" class="fuel-entry-delete" data-click="deleteFuelEntry" data-args="[${row.id}]" title="${expenseLabel("delete", "Delete")}" aria-label="${expenseLabel("delete", "Delete")}">
            <i class="fa-solid fa-trash"></i>
          </button>
        </div>
      `).join("");
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(translate("unknown_error"));
      closeFuelVehicleHistory();
    });
}

function deleteFuelEntry(expenseId) {
  if (!expenseId || !confirm(translate("confirm_delete_fuel_entry"))) {
    return;
  }

  safeFetch("endpoints/expenses/delete.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-Token": window.csrfToken,
    },
    body: JSON.stringify({ id: expenseId }),
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
        openFuelVehicleHistory(currentFuelHistoryVehicleId, currentFuelHistoryOffset);
        return;
      }

      showErrorMessage(data.message || translate("unknown_error"));
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(translate("unknown_error"));
    });
}

function openEditFuelVehicle(eventOrId, maybeId) {
  const vehicleId = typeof eventOrId === "object" ? maybeId : eventOrId;
  if (eventOrId && typeof eventOrId.stopPropagation === "function") {
    eventOrId.stopPropagation();
    eventOrId.preventDefault();
  }

  if (!vehicleId) {
    return;
  }

  safeFetch(`endpoints/fuel_vehicles/get.php?id=${encodeURIComponent(vehicleId)}`)
    .then(response => response.json())
    .then(data => {
      if (!data.success) {
        showErrorMessage(data.message || translate("unknown_error"));
        return;
      }

      const vehicle = data.vehicle;
      document.getElementById("fuelVehicleId").value = vehicle.id || "";
      document.getElementById("fuelVehicleName").value = vehicle.name || "";
      document.getElementById("fuelVehicleMake").value = vehicle.make || "";
      document.getElementById("fuelVehicleModel").value = vehicle.model || "";
      document.getElementById("fuelVehicleRegistration").value = vehicle.registration || "";
      document.getElementById("fuelVehicleFuelType").value = vehicle.fuel_type || "petrol";
      document.getElementById("fuelVehicleLogoUrl").value = vehicle.logo_url || "";
      const preview = document.getElementById("fuelVehicleLogoPreview");
      if (preview) {
        preview.src = vehicle.logo_url || "";
        preview.style.display = vehicle.logo_url ? "block" : "none";
      }
      document.getElementById("fuelVehiclePayer").value = vehicle.payer_user_id || "0";
      setFuelVehicleLogoSearchStatus();
      openFuelVehicleForm(false);
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(translate("unknown_error"));
    });
}

function deleteFuelVehicle(eventOrId, maybeId) {
  const vehicleId = typeof eventOrId === "object" ? maybeId : eventOrId;
  if (eventOrId && typeof eventOrId.stopPropagation === "function") {
    eventOrId.stopPropagation();
    eventOrId.preventDefault();
  }

  if (!vehicleId || !confirm(translate("confirm_delete_vehicle"))) {
    return;
  }

  safeFetch("endpoints/fuel_vehicles/delete.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-Token": window.csrfToken,
    },
    body: JSON.stringify({ id: vehicleId }),
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
        window.location.reload();
      } else {
        showErrorMessage(data.message || translate("unknown_error"));
      }
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(translate("unknown_error"));
    });
}

function getPetrolUnitLabel() {
  const unit = document.getElementById("petrolUnit")?.value || "l";
  return unit === "gal_us" ? "gal" : "L";
}

function updatePetrolUnitPrice() {
  const amount = parseFloat(document.getElementById("petrolAmount")?.value || "0");
  const quantity = parseFloat(document.getElementById("petrolQuantity")?.value || "0");
  const preview = document.getElementById("petrolUnitPricePreview");

  if (!preview) {
    return;
  }

  if (amount <= 0 || quantity <= 0) {
    preview.textContent = translate("unit_price_will_calculate");
    return;
  }

  const currencySelect = document.getElementById("petrolCurrency");
  const currencyName = currencySelect?.selectedOptions?.[0]?.textContent || "";
  const unitPrice = amount / quantity;
  preview.textContent = `${translate("price_per_unit")}: ${unitPrice.toFixed(3)} / ${getPetrolUnitLabel()} (${currencyName.trim()})`;
}

document.addEventListener("click", function(event) {
  const menu = document.getElementById("quickAddMenu");
  if (!menu || !menu.classList.contains("is-open")) {
    return;
  }

  if (menu.contains(event.target) || event.target.closest(".quick-add-trigger, .modern-fab")) {
    return;
  }

  closeQuickAddMenu();
});

document.addEventListener("keydown", function(event) {
  if (event.key === "Escape") {
    closeQuickAddMenu();
    closePetrolExpenseModal();
    closeFuelVehicleForm();
    closeFuelVehicleHistory();
    closeFuelVehicleLogoSearch();
  }
});

document.addEventListener("DOMContentLoaded", function() {
  openPetrolExpenseModalFromUrl();

  const form = document.getElementById("petrolExpenseForm");
  if (form) {
    form.addEventListener("submit", function(event) {
      event.preventDefault();
      const vehicleSelect = document.getElementById("petrolVehicle");
      if (vehicleSelect && vehicleSelect.disabled) {
        showErrorMessage(translate("create_petrol_vehicle_first"));
        return;
      }

      const saveButton = document.getElementById("savePetrolExpense");
      saveButton.disabled = true;

      safeFetch("endpoints/expenses/add.php", {
        method: "POST",
        headers: {
          "X-CSRF-Token": window.csrfToken,
        },
        body: new FormData(form),
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showSuccessMessage(data.message);
            form.reset();
            document.getElementById("petrolExpenseDate").value = new Date().toISOString().slice(0, 10);
            closePetrolExpenseModal();
            window.location.reload();
          } else {
            showErrorMessage(data.message || translate("unknown_error"));
          }
        })
        .catch(error => {
          console.error(error);
          showErrorMessage(translate("unknown_error"));
        })
        .finally(() => {
          saveButton.disabled = false;
        });
    });
  }

  const vehicleForm = document.getElementById("fuelVehicleCreateForm");
  if (!vehicleForm) {
    return;
  }

  vehicleForm.addEventListener("submit", function(event) {
    event.preventDefault();
    const saveButton = document.getElementById("saveFuelVehicle");
    saveButton.disabled = true;

    safeFetch("endpoints/fuel_vehicles/add.php", {
      method: "POST",
      headers: {
        "X-CSRF-Token": window.csrfToken,
      },
      body: new FormData(vehicleForm),
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showSuccessMessage(data.message);
          vehicleForm.reset();
          document.getElementById("fuelVehicleId").value = "";
          document.getElementById("fuelVehicleLogoUrl").value = "";
          closeFuelVehicleForm();
          closeFuelVehicleLogoSearch();
          window.location.reload();
        } else {
          showErrorMessage(data.message || translate("unknown_error"));
        }
      })
      .catch(error => {
        console.error(error);
        showErrorMessage(translate("unknown_error"));
      })
      .finally(() => {
        saveButton.disabled = false;
      });
  });
});

document.addEventListener("click", function(event) {
  const actionTarget = event.target.closest("[data-action]");
  if (!actionTarget || !document.contains(actionTarget)) {
    return;
  }

  const action = actionTarget.dataset.action;
  const id = actionTarget.dataset.id;
  if (action === "fuel-history") {
    event.preventDefault();
    event.stopPropagation();
    openFuelVehicleHistory(id, 0);
  } else if (action === "fuel-edit") {
    openEditFuelVehicle(event, id);
  } else if (action === "fuel-delete") {
    deleteFuelVehicle(event, id);
  } else if (action === "fuel-nfc") {
    event.preventDefault();
    event.stopPropagation();
    copyFuelVehicleNfcLink(actionTarget);
  }
});

document.addEventListener("keydown", function(event) {
  if (event.key !== "Enter" && event.key !== " ") {
    return;
  }

  const actionTarget = event.target.closest('[data-action="fuel-nfc"]');
  if (!actionTarget || !document.contains(actionTarget)) {
    return;
  }

  event.preventDefault();
  copyFuelVehicleNfcLink(actionTarget);
});
