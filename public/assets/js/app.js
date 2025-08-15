/**
 * SISTEMA DE API - JAVASCRIPT PRINCIPAL
 * Maneja autenticación, gestión de consultas y comunicación con el backend
 */

class ApiSystem {
  constructor() {
    this.baseUrl = window.location.origin + "/Api_Sistem/app/";
    this.currentUser = null;
    this.isAuthenticated = false;

    // Variables de paginación y búsqueda
    this.currentPage = 1;
    this.currentLimit = 10;
    this.currentSearch = "";
    this.totalPages = 0;

    this.init();
  }

  /**
   * Inicializar la aplicación
   */
  init() {
    this.bindEvents();
    this.checkAuthStatus();
    this.initializeTooltips();
  }

  /**
   * Vincular eventos del DOM
   */
  bindEvents() {
    // Login form
    const loginForm = document.getElementById("loginForm");
    if (loginForm) {
      loginForm.addEventListener("submit", (e) => this.handleLogin(e));
    }

    // Logout button
    const logoutBtn = document.getElementById("logoutBtn");
    if (logoutBtn) {
      logoutBtn.addEventListener("click", (e) => this.handleLogout(e));
    }

    // Navigation tabs
    const navTabs = document.querySelectorAll(".nav-link");
    navTabs.forEach((tab) => {
      tab.addEventListener("click", (e) => this.handleTabChange(e));
    });

    // Query management forms
    const createForm = document.getElementById("createQueryForm");
    if (createForm) {
      createForm.addEventListener("submit", (e) => this.handleCreateQuery(e));
    }

    const editForm = document.getElementById("editQueryForm");
    if (editForm) {
      editForm.addEventListener("submit", (e) => this.handleEditQuery(e));
    }

    // Delete buttons
    document.addEventListener("click", (e) => {
      if (e.target.classList.contains("btn-delete")) {
        this.handleDeleteQuery(e);
      }
    });

    // Edit buttons
    document.addEventListener("click", (e) => {
      if (e.target.classList.contains("btn-edit")) {
        this.handleEditClick(e);
      }
    });

    // Search functionality
    const searchInput = document.getElementById("searchInput");
    if (searchInput) {
      searchInput.addEventListener("keypress", (e) => {
        if (e.key === "Enter") {
          this.searchQueries();
        }
      });
    }

    // Limit change functionality
    const limitSelect = document.getElementById("limitSelect");
    if (limitSelect) {
      limitSelect.addEventListener("change", (e) => {
        this.changeLimit(parseInt(e.target.value));
      });
    }
  }

  /**
   * Verificar estado de autenticación
   */
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

  /**
   * Manejar login del usuario
   */
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
        headers: {
          "Content-Type": "application/json",
        },
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

        // Actualizar el nombre del usuario en la interfaz
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

  /**
   * Manejar logout del usuario
   */
  async handleLogout(e = null) {
    if (e) e.preventDefault();

    try {
      const response = await fetch(`${this.baseUrl}auth.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: "logout",
        }),
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

  /**
   * Mostrar formulario de login
   */
  showLoginForm() {
    const loginSection = document.getElementById("loginSection");
    const dashboardSection = document.getElementById("dashboardSection");
    const userInfo = document.getElementById("userInfo");

    if (loginSection) loginSection.style.display = "block";
    if (dashboardSection) dashboardSection.style.display = "none";
    if (userInfo) userInfo.style.display = "none";

    // Limpiar formulario
    const loginForm = document.getElementById("loginForm");
    if (loginForm) loginForm.reset();
  }

  /**
   * Mostrar dashboard de administración
   */
  showAdminDashboard() {
    const loginSection = document.getElementById("loginSection");
    const dashboardSection = document.getElementById("dashboardSection");
    const userInfo = document.getElementById("userInfo");

    if (loginSection) loginSection.style.display = "none";
    if (dashboardSection) dashboardSection.style.display = "block";
    if (userInfo) userInfo.style.display = "block";

    // Cargar consultas existentes
    this.loadQueries();
  }

  /**
   * Cargar consultas existentes
   */
  async loadQueries() {
    try {
      this.toggleLoading(true);

      // Construir URL con parámetros de paginación y búsqueda
      let url = `${this.baseUrl}queries.php?action=list&page=${this.currentPage}&limit=${this.currentLimit}`;
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

          // Generar paginación si hay datos
          if (data.pagination && data.pagination.pages) {
            this.generatePagination(data.pagination.pages, this.currentPage);
          }

          // Mostrar mensaje si no hay resultados
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

  /**
   * Renderizar tabla de consultas
   */
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

    queries.forEach((query) => {
      const row = document.createElement("tr");
      row.innerHTML = `
                <td><span class="badge bg-secondary">${query.id}</span></td>
                <td><strong>${this.escapeHtml(query.title)}</strong></td>
                <td>
                    <code class="text-break" style="max-width: 300px; display: block;">
                        ${this.escapeHtml(query.sql_query.substring(0, 50))}${
        query.sql_query.length > 50 ? "..." : ""
      }
                    </code>
                </td>
                <td>${new Date(query.created_at).toLocaleDateString()}</td>
                <td>${
                  query.updated_at
                    ? new Date(query.updated_at).toLocaleDateString()
                    : "-"
                }</td>
                <td>
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

  /**
   * Manejar creación de consulta
   */
  async handleCreateQuery(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const title = formData.get("title").trim();
    const sqlQuery = formData.get("sql_query").trim();

    if (!title || !sqlQuery) {
      this.showAlert("Por favor, completa todos los campos", "warning");
      return;
    }

    // Validar que sea consulta SELECT
    if (!sqlQuery.toUpperCase().startsWith("SELECT")) {
      this.showAlert("Solo se permiten consultas SELECT", "danger");
      return;
    }

    try {
      this.showLoading(true);

      const response = await fetch(`${this.baseUrl}save_query.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          title: title,
          sql_query: sqlQuery,
        }),
        credentials: "include",
      });

      const data = await response.json();

      if (data.success) {
        this.showAlert("Consulta creada exitosamente", "success");
        e.target.reset();
        this.loadQueries();
        this.switchTab("queriesTab");
      } else {
        this.showAlert(data.message || "Error al crear consulta", "danger");
      }
    } catch (error) {
      console.error("Error creating query:", error);
      this.showAlert("Error de conexión", "danger");
    } finally {
      this.showLoading(false);
    }
  }

  /**
   * Manejar edición de consulta
   */
  async handleEditQuery(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const id = formData.get("query_id");
    const title = formData.get("edit_title").trim();
    const sqlQuery = formData.get("edit_sql_query").trim();

    if (!title || !sqlQuery) {
      this.showAlert("Por favor, completa todos los campos", "warning");
      return;
    }

    // Validar que sea consulta SELECT
    if (!sqlQuery.toUpperCase().startsWith("SELECT")) {
      this.showAlert("Solo se permiten consultas SELECT", "danger");
      return;
    }

    try {
      this.showLoading(true);

      const response = await fetch(`${this.baseUrl}queries.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: "update",
          id: id,
          title: title,
          sql_query: sqlQuery,
        }),
        credentials: "include",
      });

      const data = await response.json();

      if (data.success) {
        this.showAlert("Consulta actualizada exitosamente", "success");
        this.loadQueries();
        this.switchTab("queriesTab");
      } else {
        this.showAlert(
          data.message || "Error al actualizar consulta",
          "danger"
        );
      }
    } catch (error) {
      console.error("Error updating query:", error);
      this.showAlert("Error de conexión", "danger");
    } finally {
      this.showLoading(false);
    }
  }

  /**
   * Manejar eliminación de consulta
   */
  async handleDeleteQuery(e) {
    e.preventDefault();

    const id = e.target.dataset.id;
    const title = e.target.dataset.title;

    if (
      !confirm(`¿Estás seguro de que quieres eliminar la consulta "${title}"?`)
    ) {
      return;
    }

    try {
      this.showLoading(true);

      const response = await fetch(`${this.baseUrl}queries.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: "delete",
          id: id,
        }),
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
      this.showAlert("Error de conexión", "danger");
    } finally {
      this.showLoading(false);
    }
  }

  /**
   * Manejar clic en botón editar
   */
  handleEditClick(e) {
    const id = e.target.dataset.id;
    const title = e.target.dataset.title;
    const query = e.target.dataset.query;

    // Llenar formulario de edición
    const editForm = document.getElementById("editQueryForm");
    if (editForm) {
      editForm.querySelector('[name="query_id"]').value = id;
      editForm.querySelector('[name="edit_title"]').value = title;
      editForm.querySelector('[name="edit_sql_query"]').value = query;
    }

    // Cambiar a pestaña de edición
    this.switchTab("editTab");
  }

  /**
   * Cambiar entre pestañas
   */
  handleTabChange(e) {
    e.preventDefault();

    const targetTab = e.target.dataset.tab;
    this.switchTab(targetTab);
  }

  /**
   * Cambiar a pestaña específica
   */
  switchTab(tabName) {
    // Ocultar todas las pestañas
    const tabContents = document.querySelectorAll(".tab-content");
    tabContents.forEach((content) => (content.style.display = "none"));

    // Desactivar todas las pestañas
    const navLinks = document.querySelectorAll(".nav-link");
    navLinks.forEach((link) => link.classList.remove("active"));

    // Mostrar pestaña seleccionada
    const targetContent = document.getElementById(tabName + "Content");
    if (targetContent) {
      targetContent.style.display = "block";
    }

    // Activar pestaña seleccionada
    const targetLink = document.querySelector(`[data-tab="${tabName}"]`);
    if (targetLink) {
      targetLink.classList.add("active");
    }
  }

  /**
   * Mostrar alerta
   */
  showAlert(message, type = "info") {
    // Usar la función global showToast si está disponible
    if (window.showToast) {
      window.showToast(message, type);
      return;
    }

    // Fallback: crear alerta en el contenedor de toasts
    const toastContainer = document.querySelector(".toast-container");
    if (!toastContainer) return;

    const toast = document.createElement("div");
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute("role", "alert");
    toast.setAttribute("aria-live", "assertive");
    toast.setAttribute("aria-atomic", "true");

    toast.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">
          ${message}
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    `;

    toastContainer.appendChild(toast);

    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();

    // Auto-remove after 5 seconds
    setTimeout(() => {
      if (toast.parentNode) {
        toast.remove();
      }
    }, 5000);
  }

  /**
   * Mostrar/ocultar estado de carga
   */
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

  /**
   * Inicializar tooltips de Bootstrap
   */
  initializeTooltips() {
    const tooltipTriggerList = [].slice.call(
      document.querySelectorAll('[data-bs-toggle="tooltip"]')
    );
    tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
  }

  /**
   * Escapar HTML para prevenir XSS
   */
  escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  /**
   * Buscar consultas
   */
  searchQueries(searchTerm = "") {
    this.currentSearch = searchTerm;
    this.currentPage = 1;
    this.loadQueries();
  }

  /**
   * Cambiar límite de resultados por página
   */
  changeLimit(limit) {
    this.currentLimit = limit;
    this.currentPage = 1;
    this.loadQueries();
  }

  /**
   * Cambiar página
   */
  changePage(page) {
    this.currentPage = page;
    this.loadQueries();
  }

  /**
   * Generar paginación
   */
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

  /**
   * Mostrar/ocultar elementos de carga
   */
  toggleLoading(show) {
    const loadingSpinner = document.getElementById("loadingSpinner");
    const noResults = document.getElementById("noResults");
    const queriesTable = document.querySelector(".table-responsive");

    if (loadingSpinner) loadingSpinner.style.display = show ? "block" : "none";
    if (noResults) noResults.style.display = "none";
    if (queriesTable) queriesTable.style.display = show ? "none" : "block";
  }

  /**
   * Mostrar mensaje de no resultados
   */
  showNoResults() {
    const noResults = document.getElementById("noResults");
    const queriesTable = document.querySelector(".table-responsive");

    if (noResults) noResults.style.display = "block";
    if (queriesTable) queriesTable.style.display = "none";
  }
}

// Inicializar la aplicación cuando el DOM esté listo
document.addEventListener("DOMContentLoaded", () => {
  window.apiSystem = new ApiSystem();
});

// Exportar para uso global
window.ApiSystem = ApiSystem;

// Función global para cerrar sesión (llamada desde HTML)
window.logout = async function () {
  if (window.apiSystem) {
    await window.apiSystem.handleLogout();
  } else {
    // Fallback logout
    window.location.href = "index.html";
  }
};
