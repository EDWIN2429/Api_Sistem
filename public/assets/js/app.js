/**
 * SISTEMA DE API - JAVASCRIPT OPTIMIZADO Y ORGANIZADO
 * Maneja autenticación, gestión de consultas y comunicación con el backend
 */

// ========================================
// CLASE PRINCIPAL - ApiSystem
// ========================================
class ApiSystem {
  constructor() {
    this.initializeConfiguration();
    this.initializeState();
    this.init();
  }

  // ========================================
  // INICIALIZACIÓN
  // ========================================
  initializeConfiguration() {
    const currentPort = window.location.port;
    const currentHost = window.location.hostname;

    if (currentPort === "8080") {
      this.baseUrl = `http://${currentHost}:8080/Api_Sistem/app/`;
    } else if (currentPort === "" || currentPort === "80") {
      this.baseUrl = `http://${currentHost}/Api_Sistem/app/`;
    } else {
      this.baseUrl = `http://${currentHost}:${currentPort}/Api_Sistem/app/`;
    }
  }

  initializeState() {
    this.currentUser = null;
    this.isAuthenticated = false;
    this.currentPage = 1;
    this.currentLimit = 10;
    this.currentSearch = "";
  }

  init() {
    this.bindEvents();
    this.checkAuthStatus();
  }

  // ========================================
  // MANEJO DE EVENTOS
  // ========================================
  bindEvents() {
    this.bindFormEvents();
    this.bindTableEvents();
    this.bindSearchEvents();
  }

  bindFormEvents() {
    const loginForm = document.getElementById("loginForm");
    if (loginForm) {
      loginForm.addEventListener("submit", (e) => this.handleLogin(e));
    }

    const createForm = document.getElementById("createQueryForm");
    if (createForm) {
      createForm.addEventListener("submit", (e) => this.handleCreateQuery(e));
    }

    const editForm = document.getElementById("editQueryForm");
    if (editForm) {
      editForm.addEventListener("submit", (e) => this.handleEditQuery(e));
    }
  }

  bindTableEvents() {
    // Event delegation para botones de eliminar
    document.addEventListener("click", (e) => {
      const deleteButton = e.target.closest(".btn-delete");
      if (deleteButton) {
        e.preventDefault();
        e.stopPropagation();

        const id = deleteButton.dataset.id;
        const title = deleteButton.dataset.title;

        if (id) {
          this.handleDeleteQuery(id, title);
        }
      }
    });
  }

  bindSearchEvents() {
    const searchInput = document.getElementById("searchInput");
    if (searchInput) {
      searchInput.addEventListener("keypress", (e) => {
        if (e.key === "Enter") {
          this.searchQueries();
        }
      });
    }

    const limitSelect = document.getElementById("limitSelect");
    if (limitSelect) {
      limitSelect.addEventListener("change", (e) => {
        this.changeLimit(parseInt(e.target.value));
      });
    }
  }

  // ========================================
  // AUTENTICACIÓN
  // ========================================
  async checkAuthStatus() {
    try {
      const response = await fetch(`${this.baseUrl}auth.php?action=check`, {
        method: "GET",
        credentials: "include",
      });

      if (response.ok) {
        const data = await response.json();
        if (data.success) {
          this.isAuthenticated = true;
          this.currentUser = data.user;
          this.showAdminDashboard();
        } else {
          this.showLoginForm();
        }
      } else {
        this.showLoginForm();
      }
    } catch (error) {
      console.error("Error checking auth status:", error);
      this.showLoginForm();
    }
  }

  async handleLogin(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const username = formData.get("username");
    const password = formData.get("password");

    if (!username || !password) {
      this.showAlert("Por favor, completa todos los campos", "warning");
      return;
    }

    try {
      this.showLoading(true);

      const response = await fetch(`${this.baseUrl}auth.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "login",
          username: username,
          password: password,
        }),
        credentials: "include",
      });

      const data = await response.json();

      if (data.success) {
        this.isAuthenticated = true;
        this.currentUser = data.user;

        const currentUserElement = document.getElementById("currentUser");
        if (currentUserElement) {
          currentUserElement.textContent = data.user || "Admin";
        }

        this.showAlert("Login exitoso", "success");
        this.showAdminDashboard();
        this.loadQueries();
      } else {
        this.showAlert(data.message || "Credenciales inválidas", "danger");
      }
    } catch (error) {
      console.error("Error during login:", error);
      this.showAlert("Error de conexión", "danger");
    } finally {
      this.showLoading(false);
    }
  }

  async handleLogout(e = null) {
    if (e) e.preventDefault();

    try {
      const response = await fetch(`${this.baseUrl}auth.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "logout" }),
        credentials: "include",
      });

      const data = await response.json();

      if (data.success) {
        this.isAuthenticated = false;
        this.currentUser = null;
        this.showAlert("Sesión cerrada exitosamente", "success");
        this.showLoginForm();
      }
    } catch (error) {
      console.error("Error during logout:", error);
      this.showAlert("Error al cerrar sesión", "danger");
    }
  }

  // ========================================
  // GESTIÓN DE CONSULTAS
  // ========================================
  async handleCreateQuery(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const title = formData.get("title").trim();
    const description = formData.get("description").trim();
    const sqlQuery = formData.get("sql_query").trim();

    if (!title || !sqlQuery) {
      this.showAlert(
        "Por favor, completa todos los campos obligatorios",
        "warning"
      );
      return;
    }

    if (!sqlQuery.toUpperCase().startsWith("SELECT")) {
      this.showAlert(
        "Solo se permiten consultas SELECT por seguridad",
        "danger"
      );
      return;
    }

    try {
      this.showLoading(true);

      const response = await fetch(`${this.baseUrl}queries.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "create",
          title: title,
          description: description,
          sql_query: sqlQuery,
        }),
        credentials: "include",
      });

      const data = await response.json();

      if (data.success) {
        this.showAlert("Consulta creada exitosamente", "success");
        e.target.reset();

        setTimeout(() => {
          window.location.href = "index.html";
        }, 1500);
      } else {
        this.showAlert(data.message || "Error al crear consulta", "danger");
      }
    } catch (error) {
      console.error("Error creating query:", error);
      this.showAlert("Error de conexión: " + error.message, "danger");
    } finally {
      this.showLoading(false);
    }
  }

  async handleEditQuery(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const id = formData.get("query_id");
    const title = formData.get("edit_title").trim();
    const description = formData.get("edit_description").trim();
    const sqlQuery = formData.get("edit_sql_query").trim();

    if (!title || !sqlQuery) {
      this.showAlert(
        "Por favor, completa todos los campos obligatorios",
        "warning"
      );
      return;
    }

    if (!sqlQuery.toUpperCase().startsWith("SELECT")) {
      this.showAlert(
        "Solo se permiten consultas SELECT por seguridad",
        "danger"
      );
      return;
    }

    try {
      this.showLoading(true);

      const response = await fetch(`${this.baseUrl}queries.php`, {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          id: id,
          title: title,
          description: description,
          sql_query: sqlQuery,
        }),
        credentials: "include",
      });

      const data = await response.json();

      if (data.success) {
        this.showAlert("Consulta actualizada exitosamente", "success");
        setTimeout(() => {
          window.location.href = "index.html";
        }, 1500);
      } else {
        this.showAlert(
          data.message || "Error al actualizar consulta",
          "danger"
        );
      }
    } catch (error) {
      console.error("Error updating query:", error);
      this.showAlert("Error de conexión: " + error.message, "danger");
    } finally {
      this.showLoading(false);
    }
  }

  async handleDeleteQuery(id, title) {
    if (
      !confirm(`¿Estás seguro de que quieres eliminar la consulta "${title}"?`)
    ) {
      return;
    }

    try {
      this.showLoading(true);

      const response = await fetch(`${this.baseUrl}queries.php?id=${id}`, {
        method: "DELETE",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
      });

      const data = await response.json();

      if (data.success) {
        this.showAlert("Consulta eliminada exitosamente", "success");
        this.loadQueries();
      } else {
        this.showAlert(data.message || "Error al eliminar consulta", "danger");
      }
    } catch (error) {
      console.error("Error deleting query:", error);
      this.showAlert("Error de conexión: " + error.message, "danger");
    } finally {
      this.showLoading(false);
    }
  }

  async loadQueryData(queryId) {
    try {
      const response = await fetch(`${this.baseUrl}queries.php?id=${queryId}`, {
        method: "GET",
        credentials: "include",
      });

      if (response.ok) {
        const data = await response.json();

        if (data.success) {
          const query = data.data;
          this.populateEditForm(query);
        } else {
          this.showAlert("Error al cargar los datos de la consulta", "danger");
          setTimeout(() => {
            window.location.href = "index.html";
          }, 2000);
        }
      } else {
        this.showAlert("Error al cargar los datos de la consulta", "danger");
        setTimeout(() => {
          window.location.href = "index.html";
        }, 2000);
      }
    } catch (error) {
      console.error("Error loading query data:", error);
      this.showAlert("Error de conexión", "danger");
      setTimeout(() => {
        window.location.href = "index.html";
      }, 2000);
    }
  }

  populateEditForm(query) {
    const idField = document.getElementById("query_id");
    const titleField = document.getElementById("edit_title");
    const descriptionField = document.getElementById("edit_description");
    const sqlField = document.getElementById("edit_sql_query");

    if (idField) idField.value = query.id;
    if (titleField) titleField.value = query.title;
    if (descriptionField) descriptionField.value = query.description || "";
    if (sqlField) sqlField.value = query.sql_query;
  }

  // ========================================
  // CARGA Y VISUALIZACIÓN DE CONSULTAS
  // ========================================
  async loadQueries() {
    try {
      this.toggleLoading(true);

      let url = `${this.baseUrl}queries.php?page=${this.currentPage}&limit=${this.currentLimit}`;
      if (this.currentSearch) {
        url += `&search=${encodeURIComponent(this.currentSearch)}`;
      }

      const response = await fetch(url, {
        method: "GET",
        credentials: "include",
      });

      if (response.ok) {
        const data = await response.json();

        if (data.success) {
          this.renderQueriesTable(data.data);

          if (data.pagination && data.pagination.pages) {
            this.generatePagination(data.pagination.pages, this.currentPage);
            this.showSequenceInfo(data.pagination, data.data.length);
          }

          if (data.data.length === 0) {
            this.showNoResults();
          }
        } else {
          this.showAlert("Error al cargar consultas", "danger");
        }
      } else {
        this.showAlert("Error al cargar consultas", "danger");
      }
    } catch (error) {
      console.error("Error loading queries:", error);
      this.showAlert("Error de conexión", "danger");
    } finally {
      this.toggleLoading(false);
    }
  }

  renderQueriesTable(queries) {
    const tbody = document.querySelector("#queriesTableBody");
    if (!tbody) return;

    tbody.innerHTML = "";

    if (queries.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="6" class="text-center text-muted">
            No hay consultas disponibles
          </td>
        </tr>
      `;
      return;
    }

    queries.forEach((query, index) => {
      const sequenceNumber = this.getSequenceNumber(index);

      const row = document.createElement("tr");
      row.innerHTML = `
        <td class="text-center">
          <span class="badge bg-primary rounded-pill">${sequenceNumber}</span>
        </td>
        <td>
          <strong class="text-primary">${this.escapeHtml(query.title)}</strong>
        </td>
        <td>
          <code class="text-break" style="max-width: 300px; display: block;">
            ${this.escapeHtml(query.sql_query.substring(0, 50))}${
        query.sql_query.length > 50 ? "..." : ""
      }
          </code>
        </td>
        <td class="text-center">${new Date(
          query.created_at
        ).toLocaleDateString()}</td>
        <td class="text-center">${
          query.updated_at
            ? new Date(query.updated_at).toLocaleDateString()
            : "-"
        }</td>
        <td class="text-center">
          <div class="btn-group" role="group">
            <a href="edit.html?id=${
              query.id
            }" class="btn btn-outline-primary btn-sm btn-edit">
              <i class="bi bi-pencil"></i>
            </a>
            <button class="btn btn-outline-danger btn-sm btn-delete" data-id="${
              query.id
            }" data-title="${this.escapeHtml(query.title)}">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </td>
      `;
      tbody.appendChild(row);
    });
  }

  // ========================================
  // PAGINACIÓN Y BÚSQUEDA
  // ========================================
  searchQueries(searchTerm = "") {
    this.currentSearch = searchTerm;
    this.currentPage = 1;
    this.loadQueries();
  }

  changeLimit(limit) {
    this.currentLimit = limit;
    this.currentPage = 1;
    this.loadQueries();
  }

  changePage(page) {
    this.currentPage = page;
    this.loadQueries();
  }

  getSequenceNumber(index) {
    const offset = (this.currentPage - 1) * this.currentLimit;
    return offset + index + 1;
  }

  generatePagination(totalPages, currentPage) {
    const paginationNav = document.getElementById("paginationNav");
    if (!paginationNav) return;

    let paginationHTML = `
      <ul class="pagination justify-content-center">
        <li class="page-item ${currentPage === 1 ? "disabled" : ""}">
          <a class="page-link" href="#" onclick="window.apiSystem.changePage(${
            currentPage - 1
          })">Anterior</a>
        </li>
    `;

    for (let i = 1; i <= totalPages; i++) {
      paginationHTML += `
        <li class="page-item ${i === currentPage ? "active" : ""}">
          <a class="page-link" href="#" onclick="window.apiSystem.changePage(${i})">${i}</a>
        </li>
      `;
    }

    paginationHTML += `
        <li class="page-item ${currentPage === totalPages ? "disabled" : ""}">
          <a class="page-link" href="#" onclick="window.apiSystem.changePage(${
            currentPage + 1
          })">Siguiente</a>
        </li>
      </ul>
    `;

    paginationNav.innerHTML = paginationHTML;
  }

  // ========================================
  // INTERFAZ DE USUARIO
  // ========================================
  showLoginForm() {
    const loginSection = document.getElementById("loginSection");
    const dashboardSection = document.getElementById("dashboardSection");
    const userInfo = document.getElementById("userInfo");

    if (loginSection) loginSection.style.display = "block";
    if (dashboardSection) dashboardSection.style.display = "none";
    if (userInfo) userInfo.style.display = "none";

    const loginForm = document.getElementById("loginForm");
    if (loginForm) loginForm.reset();
  }

  showAdminDashboard() {
    const loginSection = document.getElementById("loginSection");
    const dashboardSection = document.getElementById("dashboardSection");
    const userInfo = document.getElementById("userInfo");

    if (loginSection) loginSection.style.display = "none";
    if (dashboardSection) dashboardSection.style.display = "block";
    if (userInfo) userInfo.style.display = "block";

    this.loadQueries();
  }

  toggleLoading(show) {
    const loadingSpinner = document.getElementById("loadingSpinner");
    const noResults = document.getElementById("noResults");
    const queriesTable = document.querySelector(".table-responsive");

    if (loadingSpinner) loadingSpinner.style.display = show ? "block" : "none";
    if (noResults) noResults.style.display = "none";
    if (queriesTable) queriesTable.style.display = show ? "none" : "block";
  }

  showNoResults() {
    const noResults = document.getElementById("noResults");
    const queriesTable = document.querySelector(".table-responsive");

    if (noResults) noResults.style.display = "block";
    if (queriesTable) queriesTable.style.display = "none";
  }

  showSequenceInfo(pagination, totalItems) {
    const sequenceInfo = document.getElementById("sequenceInfo");
    if (!sequenceInfo) return;

    const start = (this.currentPage - 1) * this.currentLimit + 1;
    const end = start + totalItems - 1;

    sequenceInfo.textContent = `Mostrando consultas ${start} a ${end} de ${pagination.total} total`;
    sequenceInfo.style.display = "block";
  }

  // ========================================
  // UTILIDADES
  // ========================================
  showAlert(message, type = "info") {
    if (window.showToast) {
      window.showToast(message, type);
      return;
    }

    const toastContainer = document.querySelector(".toast-container");
    if (!toastContainer) return;

    const toast = document.createElement("div");
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute("role", "alert");
    toast.setAttribute("aria-live", "assertive");
    toast.setAttribute("aria-atomic", "true");

    toast.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">${message}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    `;

    toastContainer.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();

    setTimeout(() => {
      if (toast.parentNode) {
        toast.remove();
      }
    }, 5000);
  }

  showLoading(show) {
    const buttons = document.querySelectorAll('button[type="submit"]');
    buttons.forEach((button) => {
      if (show) {
        button.disabled = true;
        button.innerHTML =
          '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando...';
      } else {
        button.disabled = false;
        button.innerHTML = button.dataset.originalText || "Enviar";
      }
    });
  }

  escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }
}

// ========================================
// INICIALIZACIÓN DE LA APLICACIÓN
// ========================================
document.addEventListener("DOMContentLoaded", () => {
  window.apiSystem = new ApiSystem();
  initializePageSpecificFeatures();
});

// ========================================
// FUNCIONALIDADES ESPECÍFICAS POR PÁGINA
// ========================================
function initializePageSpecificFeatures() {
  const currentPage = window.location.pathname.split("/").pop();

  switch (currentPage) {
    case "create.html":
      initializeCreatePage();
      break;
    case "edit.html":
      initializeEditPage();
      break;
    case "index.html":
      initializeIndexPage();
      break;
  }
}

function initializeCreatePage() {
  initializeSQLValidation("sql_query", "Crear Consulta");
  initializeTitleValidation("title");
  initializeFormValidation("createQueryForm");
}

function initializeEditPage() {
  initializeSQLValidation("edit_sql_query", "Actualizar Consulta");
  initializeTitleValidation("edit_title");
  initializeFormValidation("editQueryForm");

  // Cargar datos de la consulta
  const urlParams = new URLSearchParams(window.location.search);
  const queryId = urlParams.get("id");

  if (queryId) {
    if (window.apiSystem) {
      window.apiSystem.loadQueryData(queryId);
    } else {
      setTimeout(() => {
        if (window.apiSystem) {
          window.apiSystem.loadQueryData(queryId);
        } else {
          console.error("ApiSystem no disponible");
          window.location.href = "index.html";
        }
      }, 1000);
    }
  } else {
    window.location.href = "index.html";
  }
}

function initializeIndexPage() {
  // No se necesita inicialización adicional para index.html
}

// ========================================
// FUNCIONES DE VALIDACIÓN
// ========================================
function initializeSQLValidation(inputId, buttonText) {
  const sqlInput = document.getElementById(inputId);
  if (!sqlInput) return;

  sqlInput.addEventListener("input", function () {
    const sql = this.value.trim();
    const submitBtn = document.querySelector('button[type="submit"]');

    if (sql && !sql.toUpperCase().startsWith("SELECT")) {
      this.classList.add("is-invalid");
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML =
          '<i class="bi bi-exclamation-triangle me-2"></i>Solo SELECT permitido';
      }
    } else {
      this.classList.remove("is-invalid");
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = `<i class="bi bi-check-circle me-2"></i>${buttonText}`;
      }
    }
  });
}

function initializeTitleValidation(inputId) {
  const titleInput = document.getElementById(inputId);
  if (!titleInput) return;

  titleInput.addEventListener("input", function () {
    const title = this.value.trim();
    hideTitleError(inputId);

    if (title && !/^[a-zA-Z0-9_\-\s]+$/.test(title)) {
      this.classList.add("is-invalid");
      showTitleError(inputId, "El título contiene caracteres no permitidos");
    } else {
      this.classList.remove("is-invalid");
    }
  });
}

function initializeFormValidation(formId) {
  const form = document.getElementById(formId);
  if (!form) return;

  form.addEventListener("submit", function (e) {
    const title = document
      .getElementById(this.querySelector('[name*="title"]')?.name)
      ?.value.trim();
    const sqlQuery = document
      .getElementById(this.querySelector('[name*="sql_query"]')?.name)
      ?.value.trim();

    if (!title || !sqlQuery) {
      e.preventDefault();
      alert("Por favor, completa todos los campos obligatorios");
      return false;
    }

    if (!sqlQuery.toUpperCase().startsWith("SELECT")) {
      e.preventDefault();
      alert("Solo se permiten consultas SELECT por seguridad");
      return false;
    }

    if (!/^[a-zA-Z0-9_\-\s]+$/.test(title)) {
      e.preventDefault();
      showTitleError(
        this.querySelector('[name*="title"]')?.name,
        "El título contiene caracteres no permitidos"
      );
      document
        .getElementById(this.querySelector('[name*="title"]')?.name)
        ?.focus();
      return false;
    }
  });
}

function showTitleError(inputId, message) {
  const errorAlert = document.getElementById(
    inputId.replace("title", "titleErrorAlert")
  );
  const errorMessage = document.getElementById(
    inputId.replace("title", "titleErrorMessage")
  );
  if (errorAlert && errorMessage) {
    errorMessage.textContent = message;
    errorAlert.style.display = "block";
  }
}

function hideTitleError(inputId) {
  const errorAlert = document.getElementById(
    inputId.replace("title", "titleErrorAlert")
  );
  if (errorAlert) {
    errorAlert.style.display = "none";
  }
}

// ========================================
// FUNCIONES GLOBALES
// ========================================
window.showToast = function (message, type = "info") {
  const toastContainer = document.querySelector(".toast-container");
  if (!toastContainer) return;

  const toast = document.createElement("div");
  toast.className = `toast align-items-center text-white bg-${type} border-0`;
  toast.setAttribute("role", "alert");
  toast.setAttribute("aria-live", "assertive");
  toast.setAttribute("aria-atomic", "true");

  toast.innerHTML = `
    <div class="d-flex">
      <div class="toast-body">${message}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  `;

  toastContainer.appendChild(toast);
  const bsToast = new bootstrap.Toast(toast);
  bsToast.show();

  setTimeout(() => {
    if (toast.parentNode) {
      toast.remove();
    }
  }, 5000);
};

window.refreshQueries = function () {
  if (window.apiSystem) {
    window.apiSystem.loadQueries();
  }
};

window.searchQueries = function () {
  const searchTerm = document.getElementById("searchInput")?.value.trim();
  if (window.apiSystem && searchTerm !== undefined) {
    window.apiSystem.searchQueries(searchTerm);
  }
};

window.changeLimit = function () {
  const limit = document.getElementById("limitSelect")?.value;
  if (window.apiSystem && limit) {
    window.apiSystem.changeLimit(parseInt(limit));
  }
};

window.logout = async function () {
  if (window.apiSystem) {
    await window.apiSystem.handleLogout();
  } else {
    window.location.href = "index.html";
  }
};

// Exportar para uso global
window.ApiSystem = ApiSystem;
