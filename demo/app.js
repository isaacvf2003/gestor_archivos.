const DEMO_USERS = [
  { id: 1, nombre: "Administrador", usuario: "admin", clave: "admin123", admin: true },
  { id: 2, nombre: "Roman", usuario: "roman123", clave: "roman123", admin: false }
];

const DEMO_FOLDERS = [
  { id: 1, nombre: "Contratos", parentId: null },
  { id: 2, nombre: "Imagenes", parentId: null },
  { id: 3, nombre: "Revision mensual", parentId: 1 }
];

new Vue({
  el: "#app",
  data: {
    user: null,
    login: { usuario: "", clave: "" },
    loginError: false,
    showPassword: false,
    showNewUserPassword: false,
    showUserForm: false,
    showFolderForm: false,
    showRenameForm: false,
    page: "carpetas",
    users: DEMO_USERS.map(u => ({ ...u })),
    folders: DEMO_FOLDERS.map(f => ({ ...f })),
    files: [],
    selectedUserId: "",
    currentFolderId: null,
    selectedFolderId: null,
    breadcrumb: [],
    newFolder: { nombre: "" },
    renameFolderName: "",
    renameFolderTarget: null,
    pendingFiles: [],
    previews: [],
    description: "",
    dragOver: false,
    search: "",
    statusFilter: "todos",
    previewFile: null,
    newUser: { nombre: "", usuario: "", clave: "", admin: false }
  },
  computed: {
    nonAdminUsers() {
      return this.users
        .filter(u => !u.admin)
        .sort((a, b) => a.nombre.localeCompare(b.nombre, "es"));
    },
    selectedUser() {
      return this.users.find(u => u.id === this.selectedUserId) || null;
    },
    currentFolders() {
      if (this.user && this.user.admin && !this.selectedUserId) return [];
      return this.folders.filter(f => f.parentId === this.currentFolderId);
    },
    selectedFolder() {
      return this.folders.find(f => f.id === this.selectedFolderId) || null;
    },
    visibleFiles() {
      if (!this.user || !this.selectedFolderId) return [];
      let items = this.files.filter(f => f.folderId === this.selectedFolderId);
      if (!this.user.admin) return items.filter(f => f.userId === this.user.id);
      if (!this.selectedUserId) return [];
      return items.filter(f => f.userId === this.selectedUserId);
    },
    filteredFiles() {
      const term = this.search.toLowerCase();
      return this.visibleFiles.filter(f => {
        const text = [f.name, f.description, f.comment, this.userName(f.userId)].join(" ").toLowerCase();
        const statusOk = this.statusFilter === "todos" || f.status === this.statusFilter;
        return statusOk && (!term || text.includes(term));
      });
    },
    latestFiles() {
      return [...this.files].reverse().slice(0, 8);
    },
    pendingSize() {
      return this.pendingFiles.reduce((sum, file) => sum + file.size, 0);
    }
  },
  mounted() {
    window.addEventListener("beforeunload", this.cleanupUrls);
  },
  beforeDestroy() {
    window.removeEventListener("beforeunload", this.cleanupUrls);
    this.cleanupUrls();
  },
  methods: {
    loginUser() {
      const found = this.users.find(u => u.usuario === this.login.usuario && u.clave === this.login.clave);
      if (!found) {
        this.loginError = true;
        return;
      }
      this.user = found;
      this.loginError = false;
      this.page = found.admin ? "dashboard" : "carpetas";
      this.selectedUserId = found.admin ? "" : found.id;
      this.goRoot();
    },
    quickLogin(kind) {
      if (kind === "admin") this.login = { usuario: "admin", clave: "admin123" };
      if (kind === "roman") this.login = { usuario: "roman123", clave: "roman123" };
      this.loginUser();
    },
    logout() {
      this.user = null;
      this.login = { usuario: "", clave: "" };
      this.clearPending();
      this.closePreview();
    },
    switchPage(page) {
      this.page = page;
      if (page === "carpetas") this.goRoot();
    },
    createUser() {
      const nextId = Math.max(...this.users.map(u => u.id)) + 1;
      this.users.push({ id: nextId, ...this.newUser });
      this.newUser = { nombre: "", usuario: "", clave: "", admin: false };
      this.showNewUserPassword = false;
      this.showUserForm = false;
    },
    deleteUser(user) {
      if (!confirm("Eliminar usuario y sus archivos de esta demo?")) return;
      this.files.filter(f => f.userId === user.id).forEach(f => URL.revokeObjectURL(f.url));
      this.files = this.files.filter(f => f.userId !== user.id);
      this.users = this.users.filter(u => u.id !== user.id);
      if (this.selectedUserId === user.id) this.selectedUserId = "";
    },
    viewAsUser(user) {
      this.selectedUserId = user.id;
      this.page = "carpetas";
      this.goRoot();
    },
    createFolder() {
      const name = this.newFolder.nombre.trim();
      if (!name) return;
      const nextId = this.folders.length ? Math.max(...this.folders.map(f => f.id)) + 1 : 1;
      this.folders.push({ id: nextId, nombre: name, parentId: this.currentFolderId });
      this.newFolder.nombre = "";
      this.showFolderForm = false;
    },
    openRenameFolder(folder) {
      this.renameFolderTarget = folder;
      this.renameFolderName = folder.nombre;
      this.showRenameForm = true;
    },
    renameFolder() {
      if (!this.renameFolderTarget) return;
      const name = this.renameFolderName.trim();
      if (!name) return;
      this.renameFolderTarget.nombre = name;
      this.showRenameForm = false;
    },
    deleteFolder(folder) {
      if (!confirm("Eliminar carpeta y su contenido de esta demo?")) return;
      const ids = this.collectFolderIds(folder.id);
      this.files.filter(f => ids.includes(f.folderId)).forEach(f => URL.revokeObjectURL(f.url));
      this.files = this.files.filter(f => !ids.includes(f.folderId));
      this.folders = this.folders.filter(f => !ids.includes(f.id));
      if (ids.includes(this.currentFolderId) || ids.includes(this.selectedFolderId)) this.goRoot();
    },
    collectFolderIds(id) {
      const ids = [id];
      this.folders.filter(f => f.parentId === id).forEach(child => ids.push(...this.collectFolderIds(child.id)));
      return ids;
    },
    selectFolder(folder) {
      this.currentFolderId = folder.id;
      this.selectedFolderId = folder.id;
      this.breadcrumb.push(folder);
      this.clearPending();
    },
    goBreadcrumb(index) {
      const folder = this.breadcrumb[index];
      this.breadcrumb = this.breadcrumb.slice(0, index + 1);
      this.currentFolderId = folder.id;
      this.selectedFolderId = folder.id;
      this.clearPending();
    },
    goRoot() {
      this.currentFolderId = null;
      this.selectedFolderId = null;
      this.breadcrumb = [];
      this.clearPending();
    },
    pickFiles(event) {
      this.setPending(Array.from(event.target.files || []));
    },
    dropFiles(event) {
      this.dragOver = false;
      this.setPending(Array.from(event.dataTransfer.files || []));
    },
    setPending(files) {
      this.clearPending();
      this.pendingFiles = files;
      this.previews = files.map((file, index) => ({
        index,
        key: `${file.name}-${file.size}-${index}`,
        name: file.name,
        size: file.size,
        image: file.type.startsWith("image/"),
        url: URL.createObjectURL(file)
      }));
      this.syncInput();
    },
    syncInput() {
      if (!this.$refs.fileInput || typeof DataTransfer === "undefined") return;
      const transfer = new DataTransfer();
      this.pendingFiles.forEach(file => transfer.items.add(file));
      this.$refs.fileInput.files = transfer.files;
    },
    removePending(index) {
      const files = this.pendingFiles.filter((_, i) => i !== index);
      this.setPending(files);
    },
    clearPending() {
      this.previews.forEach(p => URL.revokeObjectURL(p.url));
      this.previews = [];
      this.pendingFiles = [];
      if (this.$refs.fileInput) this.$refs.fileInput.value = "";
    },
    uploadFiles() {
      if (!this.selectedFolderId) {
        alert("Primero entra a una carpeta.");
        return;
      }
      if (!this.pendingFiles.length) {
        alert("Selecciona o arrastra al menos un archivo.");
        return;
      }
      const now = new Date();
      const date = now.toLocaleDateString("es-AR") + " " + now.toLocaleTimeString("es-AR", { hour: "2-digit", minute: "2-digit" });
      this.previews.forEach((preview, index) => {
        this.files.push({
          id: Date.now() + index,
          userId: this.user.id,
          folderId: this.selectedFolderId,
          name: preview.name,
          size: preview.size,
          image: preview.image,
          url: preview.url,
          description: this.description,
          status: "revisando",
          comment: "",
          date
        });
      });
      this.pendingFiles = [];
      this.previews = [];
      this.description = "";
      if (this.$refs.fileInput) this.$refs.fileInput.value = "";
    },
    reviewFile(file, status) {
      file.status = status;
    },
    deleteFile(file) {
      if (!confirm("Eliminar archivo de esta demo?")) return;
      URL.revokeObjectURL(file.url);
      this.files = this.files.filter(f => f.id !== file.id);
    },
    openPreview(file) {
      this.previewFile = file;
    },
    closePreview() {
      this.previewFile = null;
    },
    resetDemo() {
      if (!confirm("Reiniciar toda la demo?")) return;
      this.cleanupUrls();
      this.users = DEMO_USERS.map(u => ({ ...u }));
      this.folders = DEMO_FOLDERS.map(f => ({ ...f }));
      this.files = [];
      this.selectedUserId = this.user && !this.user.admin ? this.user.id : "";
      this.goRoot();
      this.page = this.user && this.user.admin ? "dashboard" : "carpetas";
    },
    cleanupUrls() {
      this.files.forEach(f => URL.revokeObjectURL(f.url));
      this.previews.forEach(p => URL.revokeObjectURL(p.url));
    },
    countByStatus(status) {
      return this.files.filter(f => f.status === status).length;
    },
    userName(id) {
      const found = this.users.find(u => u.id === id);
      return found ? found.nombre : "Usuario eliminado";
    },
    statusLabel(status) {
      if (status === "ok") return "OK";
      if (status === "mal") return "Mal";
      return "Revisando";
    },
    fileIcon(name) {
      const ext = String(name).split(".").pop().toUpperCase();
      return ext ? ext.slice(0, 4) : "FILE";
    },
    formatBytes(bytes) {
      if (!bytes) return "0 B";
      const units = ["B", "KB", "MB", "GB"];
      const idx = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
      return `${(bytes / Math.pow(1024, idx)).toFixed(idx ? 1 : 0)} ${units[idx]}`;
    }
  }
});
