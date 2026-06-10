
let currentGroupId = null;
let searchTimeout = null;


function openCreateModal() {
  document.getElementById("modalTitle").innerHTML =
    '<i class="fas fa-user-shield"></i> Nieuwe Rol';
  document.getElementById("groupForm").reset();
  document.getElementById("groupId").value = "";
  document.getElementById("submitBtn").innerHTML =
    '<i class="fas fa-save"></i> Toevoegen';
  document.getElementById("groupModal").style.display = "block";

  
  fetch(`?ajax=permissions&action=index`)
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const records = data.data.records;
        if (!records) {
          showAlert(
            "Fout bij laden van gegevens. Records niet gevonden.",
            "error"
          );
        }

        const $template = $(".edit-permission-item.template").first();
        $(".edit-permissions-grid").children(":not(.template)").remove();

        records.forEach(function (permission) {
          var id = permission.id;
          var description = permission.description;
          var risk_grade = permission.risk_grade;

          const $el = $template.clone().removeClass("template").show();
          $el.find("input").prop("checked", false);
          $el.find(".perm-desc").text(description);
          $el.find("label").prop("for", `perm_${id}`);
          $el.find("input").prop("id", `perm_${id}`);
          $el.find("input").prop("name", `perm_${id}`);

          
          const stars = parseInt(risk_grade) || 0;
          const colors = {
            1: "#27ae60", 
            2: "#b6e622", 
            3: "#f39c12", 
            4: "#e67e22", 
            5: "#e62222", 
          };
          const starColor = colors[stars] || "#ccc";
          $el.find(".risk-stars").empty();
          for (let i = 1; i <= 5; i++) {
            $el
              .find(".risk-stars")
              .append(
                `<i class="fa fa-star${i <= stars ? "" : "-o"}" style="color:${
                  i <= stars ? starColor : "#ccc"
                };"></i>`
              );
          }
          $el.find(".risk-stars").attr("title", `Risicograad: ${stars}/5`);

          $(".edit-permissions-grid").append($el);
        });
      }
    });
}

function editGroup(id) {
  fetch(`?ajax=user_group&action=fetch&id=${id}`)
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const group = data.data;
        document.getElementById("modalTitle").innerHTML =
          '<i class="fas fa-edit"></i> Rol Bewerken';
        document.getElementById("groupId").value = group.id;
        document.getElementById("name").value = group.name || "";
        document.getElementById("description").value = group.description || "";
        document.getElementById("submitBtn").innerHTML =
          '<i class="fas fa-save"></i> Bijwerken';
        document.getElementById("groupModal").style.display = "block";

        var ugPermissions = [];

        
        fetch(`?ajax=user_group_permissions&action=index&filters={"group_id": ${id}}`)
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              const records = data.data.records;
              if (!records) {
                showAlert(
                  "Fout bij laden van gegevens. Records niet gevonden.",
                  "error"
                );
              }

              records.forEach(function (permission) {
                ugPermissions.push(permission);
              });

              
              fetch(`?ajax=permissions&action=index`)
                .then((response) => response.json())
                .then((data) => {
                  if (data.success) {
                    const records2 = data.data.records;
                    if (!records2) {
                      showAlert(
                        "Fout bij laden van gegevens. Records niet gevonden.",
                        "error"
                      );
                    }

                    const $template = $(
                      ".edit-permission-item.template"
                    ).first();
                    $(".edit-permissions-grid")
                      .children(":not(.template)")
                      .remove();

                    records2.forEach(function (permission) {
                      var id = permission.id;
                      var description = permission.description;
                      var risk_grade = permission.risk_grade;

                      const $el = $template
                        .clone()
                        .removeClass("template")
                        .show();

                      
                      $el.find("input").prop("checked", false);
                      ugPermissions.forEach(function (permission2) {
                        if (
                          permission2.permission_id == id &&
                          permission2.value == 1
                        ) {
                          $el.find("input").prop("checked", true);
                        }
                      });

                      $el.find(".perm-desc").text(description);
                      $el.find("label").prop("for", `perm_${id}`);
                      $el.find("input").prop("id", `perm_${id}`);
                      $el.find("input").prop("name", `perm_${id}`);

                      
                      const stars = parseInt(risk_grade) || 0;
                      const colors = {
                        1: "#27ae60",
                        2: "#b6e622",
                        3: "#f39c12",
                        4: "#e67e22",
                        5: "#e62222",
                      };
                      const starColor = colors[stars] || "#ccc";
                      $el.find(".risk-stars").empty();
                      for (let i = 1; i <= 5; i++) {
                        $el
                          .find(".risk-stars")
                          .append(
                            `<i class="fa fa-star${
                              i <= stars ? "" : "-o"
                            }" style="color:${
                              i <= stars ? starColor : "#ccc"
                            };"></i>`
                          );
                      }
                      $el
                        .find(".risk-stars")
                        .attr("title", `Risicograad: ${stars}/5`);

                      $(".edit-permissions-grid").append($el);
                    });
                  }
                });
            }
          });
      } else {
        showAlert(data.message || data.error, "error");
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
    editGroup(openEdit);
  }
})();


(function () {
  const params = new URLSearchParams(window.location.search);
  const openDelete = params.get("open_delete_id");

  if (openDelete) {
    fetch(`?ajax=user_group&action=fetch&id=${openDelete}`)
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          const group = data.data;
          confirmDelete(openDelete, group.name || "Onbekende Rol");
        }
      })
      .catch((error) => {
        console.error("Failed to fetch group data:", error);
      });
  }
})();

function viewGroup(id) {
  window.location.href = `?tab=user_group_detail&id=${id}`;
}

function closeModal() {
  document.getElementById("groupModal").style.display = "none";
}


function confirmDelete(id, name, canDelete = true) {
  if (!canDelete) {
    showAlert(
      `"${name}" kan niet verwijderd worden omdat er nog gebruikers aan gekoppeld zijn.`,
      "error"
    );
    return;
  }
  currentGroupId = id;
  document.getElementById("deleteGroupName").textContent = name;
  document.getElementById("deleteModal").style.display = "block";
}

function closeDeleteModal() {
  document.getElementById("deleteModal").style.display = "none";
  currentGroupId = null;
}

function deleteGroup() {
  if (!currentGroupId) return;

  fetch(`?ajax=user_group&action=delete&id=${currentGroupId}`, {
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
  let url = "?tab=user_groups";
  if (searchTerm) {
    url += `&search=${encodeURIComponent(searchTerm)}`;
  }
  window.location.href = url;
}

function resetFilters() {
  window.location.href = "?tab=user_groups";
}


function handleFormSubmit(event) {
  event.preventDefault();

  const formData = new FormData(document.getElementById("groupForm"));
  const data = Object.fromEntries(formData);
  const isEdit = !!data.id;

  
  const submitBtn = document.getElementById("submitBtn");
  const originalText = submitBtn.innerHTML;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Bezig...';
  submitBtn.disabled = true;

  var url = `?ajax=user_group&action=store`;
  if (isEdit) {
    url = `?ajax=user_group&action=update&id=${data.id}`;
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

function exportGroups() {
  
  const searchParams = new URLSearchParams(window.location.search);
  const search = searchParams.get("search") || "";

  let url = "?export=user-groups&format=csv";
  if (search) {
    url += `&search=${encodeURIComponent(search)}`;
  }

  window.open(url, "_blank");
}


window.onclick = function (event) {
  const groupModal = document.getElementById("groupModal");
  const deleteModal = document.getElementById("deleteModal");

  if (event.target === groupModal) closeModal();
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
