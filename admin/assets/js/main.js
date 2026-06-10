


window.showAlert = function (
  message,
  type = "info",
  title = null,
  footer = null
) {
  const config = {
    text: message,
    confirmButtonText: "OK",
    confirmButtonColor: "#3c8ee0",
    allowOutsideClick: false,
    allowEscapeKey: true,
    topLayer: true,
    
    footer: footer,
    showConfirmButton: true,
  };

  switch (type) {
    case "success":
      config.icon = "success";
      config.title = title || "Succes!";
      break;
    case "error":
    case "danger":
      config.icon = "error";
      config.title = title || "Fout!";
      break;
    case "warning":
      config.icon = "warning";
      config.title = title || "Waarschuwing!";
      break;
    case "info":
    default:
      config.icon = "info";
      config.title = title || "Informatie";
      break;
  }

  return Swal.fire(config);
};


window.showConfirm = function (
  message,
  title = "Bevestiging",
  confirmText = "Ja",
  cancelText = "Annuleren"
) {
  return Swal.fire({
    title: title,
    text: message,
    icon: "question",
    showCancelButton: true,
    confirmButtonColor: "#8B0000",
    cancelButtonColor: "#6c757d",
    confirmButtonText: confirmText,
    cancelButtonText: cancelText,
    allowOutsideClick: false,
    allowEscapeKey: true,
  });
};


window.showLoading = function (message = "Laden...") {
  return Swal.fire({
    title: message,
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });
};


window.showSuccessToast = function (message) {
  const Toast = Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
      toast.addEventListener("mouseenter", Swal.stopTimer);
      toast.addEventListener("mouseleave", Swal.resumeTimer);
    },
  });

  Toast.fire({
    icon: "success",
    title: message,
  });
};


window.showErrorToast = function (message) {
  const Toast = Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 5000,
    timerProgressBar: true,
    didOpen: (toast) => {
      toast.addEventListener("mouseenter", Swal.stopTimer);
      toast.addEventListener("mouseleave", Swal.resumeTimer);
    },
  });

  Toast.fire({
    icon: "error",
    title: message,
  });
};


window.validateForm = function (formId, rules = {}) {
  const form = document.getElementById(formId);
  if (!form) return false;

  const errors = [];
  const formData = new FormData(form);

  
  const requiredFields = form.querySelectorAll("[required]");
  requiredFields.forEach((field) => {
    if (!field.value.trim()) {
      errors.push(`${field.getAttribute("name") || field.id} is verplicht`);
      field.classList.add("is-invalid");
    } else {
      field.classList.remove("is-invalid");
    }
  });

  
  Object.keys(rules).forEach((fieldName) => {
    const field = form.querySelector(`[name="${fieldName}"]`);
    if (field && field.value) {
      const rule = rules[fieldName];
      if (rule.email && !isValidEmail(field.value)) {
        errors.push(`${fieldName} moet een geldig email adres zijn`);
        field.classList.add("is-invalid");
      }
      if (rule.numeric && isNaN(field.value)) {
        errors.push(`${fieldName} moet een getal zijn`);
        field.classList.add("is-invalid");
      }
      if (rule.min && field.value.length < rule.min) {
        errors.push(
          `${fieldName} moet minimaal ${rule.min} karakters bevatten`
        );
        field.classList.add("is-invalid");
      }
    }
  });

  if (errors.length > 0) {
    showAlert(errors.join("<br>"), "error", "Validatiefouten");
    return false;
  }

  return true;
};


function isValidEmail(email) {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
}


window.ajaxRequest = function (url, options = {}) {
  const defaultOptions = {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: null,
  };

  const config = { ...defaultOptions, ...options };

  
  if (!config.hideLoading) {
    showLoading("Laden...");
  }

  return fetch(url, config)
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    })
    .then((data) => {
      if (config.hideLoading) {
        Swal.close();
      }

      if (data.success) {
        if (data.message && !config.hideMessage) {
          showSuccessToast(data.message);
        }
        return data;
      } else {
        throw new Error(data.message || "Er is een fout opgetreden");
      }
    })
    .catch((error) => {
      if (config.hideLoading) {
        Swal.close();
      }
      showErrorToast(error.message);
      throw error;
    });
};


window.submitForm = function (formId, url, options = {}) {
  const form = document.getElementById(formId);
  if (!form) return;

  form.addEventListener("submit", function (e) {
    e.preventDefault();

    if (!validateForm(formId, options.rules || {})) {
      return;
    }

    const formData = new FormData(form);
    const data = new URLSearchParams(formData);

    ajaxRequest(url, {
      method: "POST",
      body: data,
      ...options,
    })
      .then((response) => {
        if (options.onSuccess) {
          options.onSuccess(response);
        } else {
          
          if (options.redirect) {
            window.location.href = options.redirect;
          } else {
            location.reload();
          }
        }
      })
      .catch((error) => {
        if (options.onError) {
          options.onError(error);
        }
      });
  });
};


window.addTableRowActions = function (tableId, actions = {}) {
  const table = document.getElementById(tableId);
  if (!table) return;

  
  const rows = table.querySelectorAll("tbody tr");
  rows.forEach((row) => {
    const lastCell = row.querySelector("td:last-child");
    if (lastCell && !lastCell.querySelector(".action-buttons")) {
      const actionButtons = document.createElement("div");
      actionButtons.className = "action-buttons";

      Object.keys(actions).forEach((actionName) => {
        const action = actions[actionName];
        const button = document.createElement("button");
        button.className = `btn btn-sm ${action.class || "btn-primary"}`;
        button.innerHTML = `<i class="fa ${action.icon}"></i>`;
        button.title = action.title || actionName;
        button.onclick = () => action.handler(row);
        actionButtons.appendChild(button);
      });

      lastCell.appendChild(actionButtons);
    }
  });
};


window.setupSearch = function (inputId, callback) {
  const input = document.getElementById(inputId);
  if (!input) return;

  let timeout;
  input.addEventListener("input", function () {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
      callback(this.value);
    }, 300); 
  });
};


document.addEventListener("DOMContentLoaded", function () {
  

  const FALLBACK_SECONDS = 3; 

  const buttons = document.querySelectorAll(".btn");
  buttons.forEach((button) => {
    button.addEventListener("click", function () {
      const originalText = this.innerHTML;
      if (this.type === "submit" || this.classList.contains("ajax-submit")) {
        this.disabled = true;
        this.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Laden...';

        
        setTimeout(() => {
          this.disabled = false;
          this.innerHTML = originalText || this.innerHTML;
        }, FALLBACK_SECONDS * 1000);
      }
    });
  });

  
  const inputs = document.querySelectorAll("input, select, textarea");
  inputs.forEach((input) => {
    input.addEventListener("blur", function () {
      if (this.hasAttribute("required") && !this.value.trim()) {
        this.classList.add("is-invalid");
      } else {
        this.classList.remove("is-invalid");
      }
    });

    input.addEventListener("input", function () {
      this.classList.remove("is-invalid");
    });
  });

  
  const forms = document.querySelectorAll("form[data-autosave]");
  forms.forEach((form) => {
    const formId = form.id;

    
    const savedData = localStorage.getItem(`form_${formId}`);
    if (savedData) {
      try {
        const data = JSON.parse(savedData);
        Object.keys(data).forEach((key) => {
          const field = form.querySelector(`[name="${key}"]`);
          if (field) {
            field.value = data[key];
          }
        });
      } catch (e) {
        console.warn("Could not load saved form data:", e);
      }
    }

    
    form.addEventListener("input", function () {
      const formData = new FormData(form);
      const data = {};
      for (let [key, value] of formData.entries()) {
        data[key] = value;
      }
      localStorage.setItem(`form_${formId}`, JSON.stringify(data));
    });

    
    form.addEventListener("submit", function () {
      localStorage.removeItem(`form_${formId}`);
    });
  });
});

window.revertAudit = function revertAudit(id, btn) {
  showConfirm("Weet u zeker dat u deze wijziging wilt herstellen?").then(
    (result) => {
      if (result.isConfirmed) {
        const button =
          btn || document.querySelector(`[onclick*="revertAudit(${id})"]`);
        if (button) {
          button.disabled = true;
          const orig = button.innerHTML;
          button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Bezig...';
        }

        fetch(`?ajax=audit&action=revert&id=${id}`, {
          method: "POST",
        })
          .then((r) => r.json())
          .then((res) => {
            if (res.success) {
              if (typeof showAlert === "function") {
                showAlert(res.message || "Herstel gelukt", "success");
              } else {
                alert(res.message || "Herstel gelukt");
              }
              setTimeout(() => window.location.reload(), 800);
            } else {
              if (typeof showAlert === "function") {
                showAlert(res.message || "Kon niet herstellen", "error");
              } else {
                alert(res.message || "Kon niet herstellen");
              }
              if (button) {
                button.disabled = false;
                button.innerHTML = orig;
              }
            }
          })
          .catch((e) => {
            console.error(e);
            if (typeof showAlert === "function")
              showAlert("Fout bij herstellen", "error");
            else alert("Fout bij herstellen");
            if (button) {
              button.disabled = false;
              button.innerHTML = orig;
            }
          });
      }
    }
  );
};

window.escapeHtml = function(unsafe) {
  if (unsafe === null || unsafe === undefined) return "";
  return String(unsafe).replace(/[&<>"']/g, function (m) {
    return {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    }[m];
  });
}

// Export functions for global use
window.ERP = {
  showAlert,
  showConfirm,
  showLoading,
  showSuccessToast,
  showErrorToast,
  validateForm,
  ajaxRequest,
  submitForm,
  addTableRowActions,
  setupSearch,
  revertAudit,
  escapeHtml,
};
