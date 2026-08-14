import axios from 'axios';

const configuredApiBaseUrl = String(import.meta.env.VITE_API_BASE_URL || '').trim();

// VS Code/Vite development uses PHP's built-in server on port 8000.
// Production can still use a same-origin /backend path.
export const API_BASE_URL = configuredApiBaseUrl || (import.meta.env.DEV
  ? 'http://localhost:8000/backend'
  : '/backend');

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Request interceptor – attach token if present
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Response interceptor – handle 401
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

// ── Auth ──────────────────────────────────────────────
export const login = (data) => api.post('/auth/login.php', data);
export const register = (data) => api.post('/auth/register.php', data);
export const getRegistrationRoles = () =>
  api.get('/auth/registration_roles.php', {
    params: { _fresh: Date.now() },
    headers: { 'Cache-Control': 'no-cache', Pragma: 'no-cache' },
  });
export const forgotPassword = (data) => api.post('/auth/forgot_password.php', data);
export const resetPassword = (data) => api.post('/auth/reset_password.php', data);
export const validateSession = () => api.get('/auth/me.php');

// ── Users ─────────────────────────────────────────────
export const getAllUsers = () => api.get('/users/get_users.php');
export const getPendingUsers = () => api.get('/users/get_users.php?status=pending');
export const approveUser = (data) => api.post('/users/approve_user.php', data);
export const rejectUser = (data) => api.post('/users/reject_user.php', data);
export const deleteUser = (data) => api.post('/users/delete_user.php', data);
export const assignRole = (data) => api.post('/users/assign_role.php', data);
export const unlockUser = (data) => api.post('/users/unlock_user.php', data);
export const updateProfile = (data) => api.post('/auth/update_profile.php', data);
export const getProfile = (id) => api.get(`/profile/get_profile.php?id=${id}`);
export const getAboutUsers = () => api.get('/users/get_about_users.php');
export const updateAboutUserPhoto = (data) => api.post('/users/update_about_user_photo.php', data);
export const getAboutGalleryImages = () => api.get('/gallery/get_about_gallery_images.php');
export const addAboutGalleryImage = (data) => api.post('/gallery/add_about_gallery_image.php', data);
export const deleteAboutGalleryImage = (data) => api.post('/gallery/delete_about_gallery_image.php', data);

// ── Procurement ───────────────────────────────────────
export const createProcurement = (data) => api.post('/procurements/create_procurement.php', data);
export const getAllProcurements = (params = {}) =>
  api.get('/procurements/get_procurements.php', { params: { limit: 100, ...params } }).then((res) => {
    const procurements = res.data?.procurements || res.data?.data || [];
    res.data.procurements = procurements.map((p) => ({
      ...p,
      status: p.status || p.current_status || 'draft',
    }));
    return res;
  });
export const getProcurementById = (id) =>
  api.get(`/procurements/get_procurement_by_id.php?id=${id}`).then((res) => {
    const details = res.data?.data || {};
    const procurement = res.data?.procurement || details.procurement || null;
    res.data.procurement = procurement
      ? { ...procurement, status: procurement.status || procurement.current_status || 'draft' }
      : null;
    res.data.details = {
      ...details,
      procurement: res.data.procurement,
      schedule: res.data?.schedule || details.schedule || [],
      status_history: res.data?.status_history || details.status_history || [],
      bid_evals: res.data?.bid_evals || details.bid_evals || [],
      financial_approval: res.data?.financial_approval || details.financial_approval || null,
      purchase_order: res.data?.purchase_order || details.purchase_order || null,
      tracking_stage: res.data?.tracking_stage || details.tracking_stage || res.data.procurement?.tracking_stage || null,
      workflow_steps: res.data?.workflow_steps || details.workflow_steps || [],
    };
    return res;
  });
export const updateProcurement = (data) => api.post('/procurements/update_procurement.php', data);
export const updateProcurementStatus = (data) => api.post('/procurements/update_status.php', data);
export const deleteProcurement = (id) => api.post('/procurements/delete_procurement.php', { id });

// ── Procurement Time Schedule ─────────────────────────
export const getNCBSchedule = (procurementId) =>
  api.get(`/schedule/get_schedule.php?procurement_id=${procurementId}`);

export const createNCBSchedule = (data) =>
  api.post('/schedule/create_ncb_schedule.php', data);

export const updateNCBSchedule = (data) =>
  api.post('/schedule/update_schedule.php', data);

export const updateNCBPaymentDate = (data) =>
  api.post('/schedule/update_ncb_payment_date.php', data);

export const getNCBCategories = () => api.get('/schedule/get_ncb_categories.php');

export const createNCBCategory = (data) =>
  api.post('/schedule/create_ncb_category.php', data);

export const updateProcurementCategory = (data) =>
  api.post('/schedule/update_procurement_category.php', data);

export const getNCBScheduleSummary = () =>
  api.get('/schedule/get_ncb_schedule_summary.php');

export const getTaskFileTracking = (params = {}) =>
  api.get('/schedule/get_task_file_tracking.php', { params });

export const saveTaskFileTracking = (data) =>
  api.post('/schedule/save_task_file_tracking.php', data);

export const deleteTaskFileTracking = (data) =>
  api.post('/schedule/delete_task_file_tracking.php', data);

export const setCurrentScheduleTask = (data) =>
  api.post('/schedule/update_current_task.php', data);


// ── Evaluations ───────────────────────────────────────
export const getBECEvaluations = () => api.get('/evaluations/get_bec_evaluations.php');
export const submitBECEvaluation = (data) => api.post('/evaluations/submit_bec_evaluation.php', data);

// ── Specifications ────────────────────────────────────
export const getSpecifications = () => api.get('/specifications/get_specifications.php');
export const submitSpecification = (data) => api.post('/specifications/submit_specification.php', data);

// ── Financial ─────────────────────────────────────────
export const getFinancialApprovals = (params = {}) => api.get('/evaluations/get_financial_approvals.php', { params });
export const submitFinancialApproval = (data) => api.post('/evaluations/create_financial_approval.php', data);

// ── Purchase Orders ───────────────────────────────────
export const getPurchaseOrders = () => api.get('/purchase_orders/get_purchase_orders.php');
export const createPurchaseOrder = (data) => api.post('/purchase_orders/create_purchase_order.php', data);

// ── Notifications ─────────────────────────────────────
export const getNotifications = () => api.get('/notifications/get_notifications.php');
export const markNotificationRead = (data) => api.post('/notifications/mark_as_read.php', data);

// ── Reports ───────────────────────────────────────────
export const getReports = () => api.get('/reports/get_reports.php');
export const getAuditLogs = () => api.get('/audit/get_audit_logs.php');
export const getDelayAlerts = (params = {}) => api.get('/alerts/get_delay_alerts.php', { params });
export const runScheduleDelayCheck = () => api.post('/alerts/check_schedule_delay_alerts.php', {});

// ── Help / Support ────────────────────────────────────
export const getHelpTickets = () => api.get('/help/get_tickets.php');
export const submitHelpTicket = (data) => api.post('/help/create_ticket.php', data);

// ── System Settings ───────────────────────────────────
export const getSystemSettings = () => api.get('/settings/get_settings.php');
export const updateSystemSettings = (data) => api.post('/settings/update_settings.php', data);

export const getTaskDelaySettings = () => api.get('/settings/get_task_delay_settings.php');
export const saveTaskDelaySetting = (data) => api.post('/settings/save_task_delay_setting.php', data);
export const triggerBackup = () => api.post('/admin/backup.php', {});

export default api;
