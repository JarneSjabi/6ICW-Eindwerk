
let currentUserId = null;
let searchTimeout = null;


function confirmAccept(id, name) {
  currentUserId = id;
  document.getElementById("acceptUserName").textContent = name;
  document.getElementById("acceptModal").style.display = "block";
}

function closeAcceptModal() {
  document.getElementById("acceptModal").style.display = "none";
  currentUserId = null;
}


function confirmDeny(id, name) {
  currentUserId = id;
  document.getElementById("denyUserName").textContent = name;
  document.getElementById("denyModal").style.display = "block";
}

function closeDenyModal() {
  document.getElementById("denyModal").style.display = "none";
  currentUserId = null;
}


function confirmDenyAll() {
  document.getElementById("denyAllModal").style.display = "block";
}

function closeDenyAllModal() {
  document.getElementById("denyAllModal").style.display = "none";
}

function acceptUser() {
  if (!currentUserId) return;

  fetch(`?ajax=awaiting_user&action=accept&id=${currentUserId}`, {
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
      closeAcceptModal();
    })
    .catch((error) => {
      console.error("Error:", error);
      showAlert("Fout bij verwijderen", "error");
      closeAcceptModal();
    });
}

function denyUser() {
  if (!currentUserId) return;

  fetch(`?ajax=awaiting_user&action=deny&id=${currentUserId}`, {
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
      closeDenyModal();
    })
    .catch((error) => {
      console.error("Error:", error);
      showAlert("Fout bij verwijderen", "error");
      closeDenyModal();
    });
}

function denyAll() {
  fetch(`?ajax=awaiting_user&action=clear_all`, {
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
      closeDenyModal();
    })
    .catch((error) => {
      console.error("Error:", error);
      showAlert("Fout bij verwijderen", "error");
      closeDenyModal();
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
  let url = "?tab=awaiting_users";
  if (searchTerm) {
    url += `&search=${encodeURIComponent(searchTerm)}`;
  }
  window.location.href = url;
}

function resetFilters() {
  window.location.href = "?tab=awaiting_users";
}

function exportUsers() {
  
  const searchParams = new URLSearchParams(window.location.search);
  const search = searchParams.get("search") || "";

  let url = "?export=awaiting_user&format=csv";
  if (search) {
    url += `&search=${encodeURIComponent(search)}`;
  }

  window.open(url, "_blank");
}


window.onclick = function (event) {
  const acceptModal = document.getElementById("acceptModal");
  const denyModal = document.getElementById("denyModal");
  const denyAllModal = document.getElementById("denyAllModal");

  if (event.target === acceptModal) closeAcceptModal();
  if (event.target === denyAllModal) closeDenyAllModal();
  if (event.target === denyModal) closeDenyModal();
};


document.addEventListener("keydown", function (event) {
  if (event.key === "Escape") {
    closeAcceptModal();
    closeDenyModal();
    closeDenyAllModal();
  }
});
