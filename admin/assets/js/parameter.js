
let currentParameterId = null;
let searchTimeout = null;


document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('parameterUsageContainer');
    if (container) {
        loadParameterUsage();
    }
});

function loadParameterUsage() {
    const container = document.getElementById('parameterUsageContainer');
    if (!container) return;

    
    const urlParams = new URLSearchParams(window.location.search);
    const id = urlParams.get('id');
    if (!id) return;

    fetch(`?ajax=parameter&action=usage&id=${id}`)
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                renderParameterUsage(res.data);
            } else {
                container.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Fout bij laden gebruik</div>';
            }
        })
        .catch(err => {
            console.error('Error loading parameter usage', err);
            container.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Fout bij laden gebruik</div>';
        });
}

function renderParameterUsage(data) {
    const container = document.getElementById('parameterUsageContainer');
    if (!container) return;

    const { product_groups = [], products_with_override = [] } = data;

    if (product_groups.length === 0) {
        container.innerHTML = `
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                Deze parameter is nog niet gekoppeld aan groepen. Bewerk of open detailweergave van een groep om een parameter toe te voegen.
            </div>
        `;
        return;
    }

    let html = '';

    
    html += `
        <h4 class="mb-3"><i class="fas fa-layer-group"></i> Toewijzingen aan groepen (${product_groups.length})</h4>
        <div class="table-responsive mb-4">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Groep</th>
                        <th>Standaardwaarde</th>
                        <th>Aantal Producten</th>
                        <th>Aantal Overrides</th>
                    </tr>
                </thead>
                <tbody>
    `;

    product_groups.forEach(pg => {
        const overrideCount = pg.override_count || 0;
        const productCount = pg.product_count || 0;
        const inheritancePercentage = productCount ? Math.round((productCount - overrideCount) / productCount * 100) : 0;

        html += `
            <tr>
                <td>
                    <a href="?tab=product_group_detail&id=${pg.id}" class="text-decoration-none">
                        ${escapeHtml(pg.name)}
                    </a>
                </td>
                <td>
                    <code class="bg-light px-2 py-1 rounded">
                        ${escapeHtml(pg.default_value || '—')}
                    </code>
                </td>
                <td>${productCount}</td>
                <td>
                    <div class="progress" style="height: 15px;width:150px">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: ${inheritancePercentage}%" 
                             title="${productCount - overrideCount} producten erven de waarde">
                        </div>
                        <div class="progress-bar bg-warning" role="progressbar" 
                             style="width: ${100 - inheritancePercentage}%"
                             title="${overrideCount} producten hebben een override">
                        </div>
                    </div>
                    <small class="text-muted">${overrideCount} overrides</small>
                </td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    
    if (products_with_override.length > 0) {
        html += `
            <h4 class="mb-3"><i class="fas fa-edit"></i> Producten met Override (${products_with_override.length})</h4>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>Groep</th>
                            <th>Override Waarde</th>
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        products_with_override.forEach(p => {
            html += `
                <tr>
                    <td>
                        <a href="?tab=product_detail&id=${p.id}" class="text-decoration-none">
                            ${escapeHtml(p.name)}
                        </a>
                    </td>
                    <td>
                        <a href="?tab=product_group_detail&id=${p.group_id}" class="badge bg-info text-decoration-none">
                            ${escapeHtml(p.group_name)}
                        </a>
                    </td>
                    <td>
                        <code class="bg-light px-2 py-1 rounded">
                            ${escapeHtml(p.value || '—')}
                        </code>
                    </td>
                    <td>
                        <a href="?tab=product_detail&id=${p.id}#parameters" 
                           class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-edit"></i> Bewerken
                        </a>
                    </td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
        `;
    }

    container.innerHTML = html;
}


function openCreateModal() {
  document.getElementById("modalTitle").innerHTML =
    '<i class="fas fa-plus"></i> Nieuwe Parameter';
  document.getElementById("parameterForm").reset();
  document.getElementById("parameterId").value = "";
  document.getElementById("submitBtn").innerHTML =
    '<i class="fas fa-save"></i> Toevoegen';
  document.getElementById("parameterModal").style.display = "block";
}

function editParameter(id) {
  fetch(`?ajax=parameter&action=fetch&id=${id}`)
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const parameter = data.data;
        document.getElementById("modalTitle").innerHTML =
          '<i class="fas fa-edit"></i> Parameter Bewerken';
        document.getElementById("parameterId").value = parameter.id;
        document.getElementById("name").value = parameter.name || "";
        document.getElementById("label").value = parameter.label || "";
        document.getElementById("description").value =
          parameter.description || "";
        document.getElementById("data_type").value = parameter.data_type || "";
        document.getElementById("default_value").value =
          parameter.default_value || "";
        $el = $("#digi_builtin");
        if (parameter.digi_builtin == 1) {
          $el.prop("checked", true);
        } else {
          $el.prop("checked", false);
        }
        
        document.getElementById("submitBtn").innerHTML =
          '<i class="fas fa-save"></i> Bijwerken';
        document.getElementById("parameterModal").style.display = "block";
      } else {
        showAlert(data.message, "error");
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      showAlert("Fout bij laden van gegevens", "error");
    });
}


(function () {
  const params = new URLSearchParams(window.location.search);
  const openEdit = params.get("open_edit_id");
  if (openEdit) {
    editParameter(openEdit);
  }
})();

function viewParameter(id) {
  
  window.location.href = `?tab=parameter_detail&id=${id}`;
}

function closeModal() {
  document.getElementById("parameterModal").style.display = "none";
}


function confirmDelete(id, name) {
  currentParameterId = id;
  document.getElementById("deleteParameterName").textContent = name;
  document.getElementById("deleteModal").style.display = "block";
}

function closeDeleteModal() {
  document.getElementById("deleteModal").style.display = "none";
  currentParameterId = null;
}

function deleteParameter() {
  if (!currentParameterId) return;

  fetch(`?ajax=parameter&action=delete&id=${currentParameterId}`, {
    method: "POST",
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showAlert(data.message, "success");
        setTimeout(() => location.reload(), 1000);
      } else {
        showAlert(data.message || data.error, "error");
      }
      closeDeleteModal();
    })
    .catch((error) => {
      console.error("Error:", error);
      showAlert("Fout bij verwijderen", "error");
      closeDeleteModal();
    });
}


function handleSearch(event) {
  if (event.key === "Enter") {
    performSearch();
  } else {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(performSearch, 500);
  }
}

function performSearch() {
  const searchTerm = document.getElementById("searchInput").value;
  let url = "?tab=parameters";
  if (searchTerm) {
    url += `&search=${encodeURIComponent(searchTerm)}`;
  }
  window.location.href = url;
}

function resetFilters() {
  window.location.href = "?tab=parameters";
}


function handleFormSubmit(event) {
  event.preventDefault();

  const formData = new FormData(document.getElementById("parameterForm"));
  const data = Object.fromEntries(formData);
  const isEdit = !!data.id;

  
  const submitBtn = document.getElementById("submitBtn");
  const originalText = submitBtn.innerHTML;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Bezig...';
  submitBtn.disabled = true;

  var url = `?ajax=parameter&action=store`;
  if (isEdit) {
    url = `?ajax=parameter&action=update&id=${data.id}`;
  }

  fetch(url, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(data),
  })
    .then((response) => response.json())
    .then((result) => {
      if (result.success) {
        showAlert(result.message, "success");
        setTimeout(() => {
          closeModal();
          location.reload();
        }, 1000);
      } else {
        showAlert(result.message || result.error, "error");
        
        if (result.data && result.data.errors) {
          Object.keys(result.data.errors).forEach((field) => {
            const errorElement = document.getElementById(field + "Error");
            if (errorElement) {
              errorElement.textContent = result.data.errors[field][0];
            }
          });
        }
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      showAlert("Fout bij opslaan", "error");
    })
    .finally(() => {
      submitBtn.innerHTML = originalText;
      submitBtn.disabled = false;
    });
}

function exportParameters() {
  
  const searchParams = new URLSearchParams(window.location.search);
  const search = searchParams.get("search") || "";

  let url = "?export=parameters&format=csv";
  if (search) {
    url += `&search=${encodeURIComponent(search)}`;
  }

  window.open(url, "_blank");
}


window.onclick = function (event) {
  const parameterModal = document.getElementById("parameterModal");
  const deleteModal = document.getElementById("deleteModal");

  if (event.target === parameterModal) closeModal();
  if (event.target === deleteModal) closeDeleteModal();
};


document.addEventListener("keydown", function (event) {
  if (event.key === "Escape") {
    closeModal();
    closeDeleteModal();
  }
  if (event.ctrlKey && event.key === "n") {
    event.preventDefault();
    openCreateModal();
  }
});
