
let currentUserId = null;
let searchTimeout = null;


function openCreateModal() {
  document.getElementById("modalTitle").innerHTML =
    '<i class="fas fa-plus"></i> Nieuwe Gebruiker';
  document.getElementById("userForm").reset();
  document.getElementById("userId").value = "";
  document.getElementById("submitBtn").innerHTML =
    '<i class="fas fa-save"></i> Toevoegen';
  document.getElementById("userModal").style.display = "block";
}

function editUser(id) {
  fetch(`?ajax=user&action=fetch&id=${id}`)
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const user = data.data;
        document.getElementById("modalTitle").innerHTML =
          '<i class="fas fa-edit"></i> Gebruiker Bewerken';
        document.getElementById("userId").value = user.id;
        document.getElementById("firstname").value = user.firstname || "";
        document.getElementById("lastname").value = user.lastname || "";
        document.getElementById("email").value = user.email || "";
        document.getElementById("user_group_id").value = user.user_group_id || "";
        
        
        document.getElementById("passwordResetSection").style.display = "block";
        document.getElementById("new_password").value = "";
        document.getElementById("confirm_password").value = "";
        
        document.getElementById("submitBtn").innerHTML =
          '<i class="fas fa-save"></i> Bijwerken';
        document.getElementById("userModal").style.display = "block";
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
    editUser(openEdit);
  }
})();

function viewUser(id) {
  
  window.location.href = `?tab=user_detail&id=${id}`;
}

function closeModal() {
  document.getElementById("userModal").style.display = "none";
}


function confirmDelete(id, name) {
  currentUserId = id;
  document.getElementById("deleteUserName").textContent = name;
  document.getElementById("deleteModal").style.display = "block";
}

function closeDeleteModal() {
  document.getElementById("deleteModal").style.display = "none";
  currentUserId = null;
}

function deleteUser() {
  if (!currentUserId) return;

  fetch(`?ajax=user&action=delete&id=${currentUserId}`, {
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
  let url = "?tab=users";
  if (searchTerm) {
    url += `&search=${encodeURIComponent(searchTerm)}`;
  }
  window.location.href = url;
}

function resetFilters() {
  window.location.href = "?tab=users";
}


function handleFormSubmit(event) {
  event.preventDefault();

  const formData = new FormData(document.getElementById("userForm"));
  const data = Object.fromEntries(formData);
  const isEdit = !!data.id;

  
  if (isEdit) {
    const newPassword = data.new_password;
    const confirmPassword = data.confirm_password;
    
    if (newPassword || confirmPassword) {
      if (!newPassword || !confirmPassword) {
        showAlert("Beide wachtwoordvelden moeten ingevuld worden", "error");
        return;
      }
      
      if (newPassword.length < 8) {
        showAlert("Wachtwoord moet minimaal 8 karakters lang zijn", "error");
        return;
      }
      
      if (newPassword !== confirmPassword) {
        showAlert("Wachtwoorden komen niet overeen", "error");
        return;
      }
      
      
      data.password = newPassword;
    }
    
    
    delete data.new_password;
    delete data.confirm_password;
  }

  
  const submitBtn = document.getElementById("submitBtn");
  const originalText = submitBtn.innerHTML;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Bezig...';
  submitBtn.disabled = true;

  var url = `?ajax=user&action=store`;
  if (isEdit) {
    url = `?ajax=user&action=update&id=${data.id}`;
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

function exportUsers() {
  
  const searchParams = new URLSearchParams(window.location.search);
  const search = searchParams.get("search") || "";

  let url = "?export=users&format=csv";
  if (search) {
    url += `&search=${encodeURIComponent(search)}`;
  }

  window.open(url, "_blank");
}


window.onclick = function (event) {
  const userModal = document.getElementById("userModal");
  const deleteModal = document.getElementById("deleteModal");

  if (event.target === userModal) closeModal();
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
