import { useEffect, useState } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import Layout from '../../components/Layout';
import Icon from '../../components/Icon';
import api, { API_BASE_URL } from '../../api/api';
import { getUser } from '../../utils/auth';
import '../../styles/forms.css';
import '../../styles/tables.css';

const DEFAULT_SCHEDULE_TASKS = [
  { task_name: 'Appoint Specification Preparation Committee', responsible_role: 'Director / Procurement Branch' },
  { task_name: 'Submit finalized specification to Procurement Branch', responsible_role: 'Specification Preparation Committee' },
  { task_name: 'Appoint Bid Evaluation Committee (BEC)', responsible_role: 'Director / Procurement Branch' },
  { task_name: 'Prepare bidding / quotation document', responsible_role: 'Procurement Officer' },
  { task_name: 'Send procurement document to relevant committee for review', responsible_role: 'Procurement Officer / BEC' },
  { task_name: 'Publish / call bids or quotations', responsible_role: 'Procurement Officer' },
  { task_name: 'Conduct pre-bid meeting, if required', responsible_role: 'Procurement Officer / Committee' },
  { task_name: 'Bid / quotation opening', responsible_role: 'Bid Opening Committee / Procurement Officer' },
  { task_name: 'Send bid / quotation documents to Evaluation Committee', responsible_role: 'Procurement Officer' },
  { task_name: 'Submit evaluation report and award recommendation', responsible_role: 'BEC' },
  { task_name: 'Tender Decision', responsible_role: 'Procurement Committee' },
  { task_name: 'Appeal Committee', responsible_role: 'Procurement Committee' },
  { task_name: 'Issue purchase order / letter of award', responsible_role: 'Procurement Officer' },
  { task_name: 'Stock Received', responsible_role: 'Procurement Committee' },
  { task_name: 'Receive acceptance letter from supplier', responsible_role: 'Supplier / Procurement Officer' },
];

const STATUS_OPTIONS = [
  { value: 'pending', label: 'Pending' },
  { value: 'in_progress', label: 'In Progress' },
  { value: 'completed', label: 'Completed' },
  { value: 'delayed', label: 'Delayed' },
  { value: 'skipped', label: 'Skipped / Not Necessary' },
];

const FILE_TYPE_OPTIONS = [
  'Main Procurement File',
  'Specification File',
  'Bid / Quotation Document',
  'Committee Letter',
  'Bid Opening Document',
  'Evaluation Report',
  'Approval Document',
  'Supplier Document',
  'Purchase Order',
  'Invoice / Payment File',
  'Other',
];

function emptyTask(task = {}, index = 0) {
  return {
    id: null,
    task_name: task.task_name || '',
    responsible_role: task.responsible_role || '',
    planned_date: '',
    allowed_delay_days: 0,
    actual_date: '',
    status: 'pending',
    remarks: '',
    sort_order: index + 1,
    delay_info: null,
    file_tracking_summary: { type_count: 0, total_files: 0, completed_files: 0, pending_files: 0 },
  };
}

function formatDateForInput(value) {
  if (!value) return '';
  return String(value).slice(0, 10);
}

function todayLocal() {
  const date = new Date();
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function dateToMidnight(value) {
  if (!value) return null;
  return new Date(`${String(value).slice(0, 10)}T00:00:00`);
}

function daysBetween(start, end) {
  const startDate = dateToMidnight(start);
  const endDate = dateToMidnight(end);
  if (!startDate || !endDate) return 0;
  return Math.floor((endDate - startDate) / 86400000);
}

function normalizeDelayDays(value) {
  if (value === '' || value === null || value === undefined) return 0;
  return Math.max(0, Math.min(3650, Number.parseInt(value, 10) || 0));
}

function addDays(value, days) {
  const date = dateToMidnight(value);
  if (!date) return null;
  date.setDate(date.getDate() + normalizeDelayDays(days));
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function normalizeRemarkForUnskip(remarks = '') {
  return String(remarks).replace(/^Skipped:\s*/i, '').trim();
}

function getClientDelayInfo(task) {
  if (!task?.planned_date || task.status === 'skipped') return null;
  const allowedDelayDays = normalizeDelayDays(task.allowed_delay_days);
  const deadlineDate = addDays(task.planned_date, allowedDelayDays);
  const comparisonDate = task.actual_date || todayLocal();
  const lateDays = daysBetween(deadlineDate, comparisonDate);
  if (lateDays < 1) return null;
  if (!task.actual_date && deadlineDate >= todayLocal()) return null;

  return {
    is_delayed: true,
    days_late: lateDays,
    allowed_delay_days: allowedDelayDays,
    deadline_date: deadlineDate,
    alert_color: lateDays >= 22 ? 'red' : 'yellow',
    label: task.actual_date ? 'Completed Late' : 'Delay',
    description: task.actual_date
      ? `Actual date is ${lateDays} day(s) after allowed deadline ${deadlineDate}.`
      : `Allowed deadline ${deadlineDate} is overdue by ${lateDays} day(s).`,
  };
}

function getCommitteeTypeForTask(taskOrName = '') {
  const taskName = typeof taskOrName === 'object' && taskOrName !== null
    ? taskOrName.task_name
    : taskOrName;
  const name = String(taskName || '').toLowerCase();

  if (name.includes('bid evaluation committee') || name.includes('(bec)')) return 'BEC';
  if (name.includes('specification preparation committee') || name.includes('specification committee')) return 'Specification';

  // Safety fallback for older database rows where the task name was edited/truncated.
  // In the standard NCB schedule task 1 is Specification Committee appointment and task 3 is BEC appointment.
  if (typeof taskOrName === 'object' && taskOrName !== null) {
    const order = Number(taskOrName.sort_order || 0);
    const index = Number.isInteger(taskOrName.index) ? taskOrName.index : -1;
    if (order === 1 || index === 0) return 'Specification';
    if (order === 3 || index === 2) return 'BEC';
  }

  return null;
}

function getCommitteeDisplayName(committeeType) {
  return committeeType === 'BEC' ? 'Bid Evaluation Committee (BEC)' : 'Specification Preparation Committee';
}

function getShortCommitteeName(committeeType) {
  return committeeType === 'BEC' ? 'BEC' : 'Specification Committee';
}

function statusText(value) {
  const map = {
    sent: 'sent',
    failed: 'failed',
    not_sent: 'not sent',
    pending: 'pending',
    mail_client_opened: 'mail app opened',
    sent_manually: 'sent manually',
  };
  return map[value] || value || 'not sent';
}

function emptyFileTrackingState() {
  return {
    loading: false,
    saving: false,
    entries: [],
    summary: { type_count: 0, total_files: 0, completed_files: 0, pending_files: 0 },
    editingId: null,
    fileType: '',
    totalFiles: '',
    completedFiles: '',
    remarks: '',
  };
}

function fileTrackingSummaryText(summary = {}) {
  const total = Number(summary.total_files || 0);
  const done = Number(summary.completed_files || 0);
  const pending = Number(summary.pending_files || 0);
  const types = Number(summary.type_count || 0);
  if (!total && !types) return 'No files tracked';
  return `${total} file(s) • ${done} done • ${pending} pending • ${types} type(s)`;
}

function normalizeNumberInput(value) {
  if (value === '' || value === null || value === undefined) return '';
  return Math.max(0, Number.parseInt(value, 10) || 0);
}

function blankCommitteeAppointmentState() {
  return {
    loading: false,
    saving: false,
    candidates: [],
    appointments: [],
    chairmanUserId: '',
    memberUserIds: [],
    letterDate: todayLocal(),
    plannedDate: '',
    role: '',
    emailResults: [],
    selectionUserId: '',
    selectionPosition: 'Member',
    manualName: '',
    manualEmail: '',
    manualDesignation: '',
    manualPosition: 'Member',
    manualSaving: false,
    emptyMessage: '',
    blockedCandidates: [],
    pendingRequestedCandidates: [],
    roleSummary: null,
    smtpStatus: null,
  };
}

export default function NCBTimeSchedule() {
  const navigate = useNavigate();
  const { id } = useParams();
  const [searchParams] = useSearchParams();
  const procurementId = id || searchParams.get('procurement_id');
  const currentUser = getUser();
  const isITAdmin = currentUser?.role === 'it_admin';

  const [procurement, setProcurement] = useState(null);
  const [categories, setCategories] = useState([]);
  const [selectedCategory, setSelectedCategory] = useState('');
  const [newCategory, setNewCategory] = useState('');
  const [paymentDate, setPaymentDate] = useState('');
  const [tasks, setTasks] = useState(DEFAULT_SCHEDULE_TASKS.map((task, index) => emptyTask(task, index)));
  const [stats, setStats] = useState({ total: 0, completed: 0, delayed: 0, skipped: 0, applicable: 0, progress: 0, tracked_files: 0, completed_files: 0, pending_files: 0 });
  const [newTask, setNewTask] = useState('');
  const [taskModal, setTaskModal] = useState(null);
  const [fileTracking, setFileTracking] = useState(emptyFileTrackingState());
  const [committeeAppointment, setCommitteeAppointment] = useState(blankCommitteeAppointmentState());
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [categorySaving, setCategorySaving] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  const loadCategories = async () => {
    try {
      const response = await api.get('/schedule/get_ncb_categories.php');
      setCategories(response.data?.categories || []);
    } catch {
    }
  };

  const loadSchedule = async () => {
    if (!procurementId) {
      setError('Procurement ID is missing. Open this page from Procurement Management.');
      return;
    }

    setLoading(true);
    setError('');
    setMessage('');

    try {
      const response = await api.get(`/schedule/get_schedule.php?procurement_id=${procurementId}`);
      const result = response.data;

      if (!result.success) {
        setError(result.message || 'Failed to load procurement time schedule.');
        return;
      }

      setProcurement(result.procurement || null);
      setSelectedCategory(result.procurement?.category || '');
      setPaymentDate(formatDateForInput(result.procurement?.payment_date));
      setStats(result.stats || { total: 0, completed: 0, delayed: 0, skipped: 0, applicable: 0, progress: 0, tracked_files: 0, completed_files: 0, pending_files: 0 });

      const scheduleData = Array.isArray(result.data) ? result.data : [];
      if (scheduleData.length > 0) {
        setTasks(
          scheduleData.map((item, index) => ({
            id: item.id,
            task_name: item.task_name || '',
            responsible_role: item.responsible_role || '',
            planned_date: formatDateForInput(item.planned_date),
            allowed_delay_days: normalizeDelayDays(item.allowed_delay_days),
            actual_date: formatDateForInput(item.actual_date),
            status: item.status || 'pending',
            remarks: item.remarks || '',
            sort_order: item.sort_order || index + 1,
            delay_info: item.delay_info || null,
            file_tracking_summary: item.file_tracking_summary || { type_count: 0, total_files: 0, completed_files: 0, pending_files: 0 },
          }))
        );
      } else {
        setTasks(DEFAULT_SCHEDULE_TASKS.map((task, index) => emptyTask(task, index)));
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Cannot connect to schedule backend.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadSchedule();
    loadCategories();
  }, [procurementId]);

  const handleTaskChange = (index, field, value) => {
    setTasks((prev) => {
      const updated = [...prev];
      const previousValue = updated[index]?.[field] || '';
      updated[index] = { ...updated[index], [field]: value };

      if (field === 'actual_date' && updated[index + 1] && (!updated[index + 1].planned_date || updated[index + 1].planned_date === previousValue)) {
        updated[index + 1] = { ...updated[index + 1], planned_date: value };
      }

      if ((field === 'planned_date' || field === 'actual_date') && getClientDelayInfo(updated[index]) && updated[index].status !== 'skipped') {
        updated[index] = { ...updated[index], status: 'delayed' };
      }

      return updated;
    });
  };

  const saveTask = async (task) => {
    if (!task.id) return;
    await api.post('/schedule/update_schedule.php', {
      task_id: task.id,
      planned_date: task.planned_date,
      allowed_delay_days: normalizeDelayDays(task.allowed_delay_days),
      actual_date: task.actual_date,
      status: task.status,
      remarks: task.remarks,
    });
  };

  const setCurrentScheduleTaskForRow = async (task, index) => {
    if (!procurementId || !task?.id) {
      setError('Save/load this schedule before setting the current task.');
      return;
    }

    const confirmed = window.confirm(`Set Task ${index + 1} as the current location for this procurement?`);
    if (!confirmed) return;

    setSaving(true);
    setError('');
    setMessage('');

    try {
      await api.post('/schedule/update_current_task.php', {
        procurement_id: procurementId,
        schedule_task_id: task.id,
        remarks: `Current location set to Task ${index + 1}: ${task.task_name}.`,
      });
      setMessage('Current task/location updated successfully. Location summary cards will now count this file under the task committee.');
      await loadSchedule();
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to update current task/location.');
    } finally {
      setSaving(false);
    }
  };

  const saveAllTasks = async () => {
    setSaving(true);
    setError('');
    setMessage('');

    try {
      const savedTasks = tasks.filter((task) => task.id);
      await Promise.all(savedTasks.map((task) => saveTask(task)));
      setMessage('Procurement time schedule updated successfully. Skipped tasks keep their dates and remarks.');
      await loadSchedule();
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to update schedule.');
    } finally {
      setSaving(false);
    }
  };

  const skipTask = async (index) => {
    const task = tasks[index];
    const reason = window.prompt('Enter reason for skipping this task:', normalizeRemarkForUnskip(task.remarks));
    if (reason === null) return;

    const updatedTask = {
      ...task,
      status: 'skipped',
      remarks: reason.trim() ? `Skipped: ${reason.trim()}` : 'Skipped: Not necessary for this procurement type.',
    };

    const updated = [...tasks];
    updated[index] = updatedTask;
    setTasks(updated);

    if (updatedTask.id) {
      setSaving(true);
      setError('');
      try {
        await saveTask(updatedTask);
        setMessage('Task skipped successfully. Existing dates and remarks were kept.');
        await loadSchedule();
      } catch (err) {
        setError(err.response?.data?.message || 'Failed to skip task.');
      } finally {
        setSaving(false);
      }
    }
  };

  const unskipTask = async (index) => {
    const task = tasks[index];
    const updatedTask = {
      ...task,
      status: 'pending',
      remarks: normalizeRemarkForUnskip(task.remarks),
    };

    const updated = [...tasks];
    updated[index] = updatedTask;
    setTasks(updated);

    if (updatedTask.id) {
      setSaving(true);
      setError('');
      try {
        await saveTask(updatedTask);
        setMessage('Task restored to pending. Existing dates were kept.');
        await loadSchedule();
      } catch (err) {
        setError(err.response?.data?.message || 'Failed to restore task.');
      } finally {
        setSaving(false);
      }
    }
  };

  const addNewTask = async () => {
    const taskName = newTask.trim();
    if (!taskName) {
      setError('Please enter a task name.');
      return;
    }

    if (!procurementId) {
      setError('Procurement ID is missing.');
      return;
    }

    setSaving(true);
    setError('');
    setMessage('');

    try {
      await api.post('/schedule/create_ncb_schedule.php', {
        procurement_id: procurementId,
        task_name: taskName,
        planned_date: '',
        allowed_delay_days: 0,
        remarks: '',
      });
      setNewTask('');
      setMessage('New schedule task added successfully.');
      await loadSchedule();
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to add new task.');
    } finally {
      setSaving(false);
    }
  };

  const addCategory = async () => {
    const categoryName = newCategory.trim();
    if (!categoryName) {
      setError('Please enter a category name.');
      return;
    }

    setCategorySaving(true);
    setError('');
    setMessage('');

    try {
      await api.post('/schedule/create_ncb_category.php', { category_name: categoryName });
      setNewCategory('');
      setSelectedCategory(categoryName);
      setMessage('Category added successfully. Select it and save the category for this schedule.');
      await loadCategories();
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to add category. Only IT Admin can add categories.');
    } finally {
      setCategorySaving(false);
    }
  };

  const saveScheduleCategory = async () => {
    if (!procurementId) {
      setError('Procurement ID is missing.');
      return;
    }

    if (!selectedCategory) {
      setError('Please select a category.');
      return;
    }

    setCategorySaving(true);
    setError('');
    setMessage('');

    try {
      await api.post('/schedule/update_procurement_category.php', {
        procurement_id: procurementId,
        category: selectedCategory,
      });
      setMessage('Schedule category updated successfully.');
      await loadSchedule();
      await loadCategories();
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to update schedule category. Only IT Admin can update it here.');
    } finally {
      setCategorySaving(false);
    }
  };

  const savePaymentDate = async () => {
    if (!procurementId) {
      setError('Procurement ID is missing.');
      return;
    }

    setSaving(true);
    setError('');
    setMessage('');

    try {
      await api.post('/schedule/update_ncb_payment_date.php', {
        procurement_id: procurementId,
        payment_date: paymentDate,
      });
      setMessage('Payment date updated successfully.');
      await loadSchedule();
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to update payment date.');
    } finally {
      setSaving(false);
    }
  };

  const resetToStandardTasks = async () => {
    if (!procurementId) {
      setError('Procurement ID is missing.');
      return;
    }

    const confirmReset = window.confirm(
      'This will replace the current schedule with the 15 standard NCB schedule tasks. Continue?'
    );
    if (!confirmReset) return;

    setSaving(true);
    setError('');
    setMessage('');

    try {
      await api.post('/schedule/reset_default_ncb_schedule.php', {
        procurement_id: procurementId,
      });
      setMessage('Schedule reset with the 15 standard NCB tasks.');
      await loadSchedule();
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to reset schedule tasks.');
    } finally {
      setSaving(false);
    }
  };

  const resetFileTrackingForm = () => {
    setFileTracking((prev) => ({
      ...prev,
      editingId: null,
      fileType: '',
      totalFiles: '',
      completedFiles: '',
      remarks: '',
    }));
  };

  const loadFileTracking = async (task = taskModal) => {
    if (!procurementId || !task?.id) {
      setFileTracking(emptyFileTrackingState());
      return;
    }

    setFileTracking((prev) => ({ ...prev, loading: true, saving: false }));
    try {
      const response = await api.get('/schedule/get_task_file_tracking.php', {
        params: { procurement_id: procurementId, task_id: task.id },
      });
      const result = response.data || {};
      setFileTracking((prev) => ({
        ...prev,
        loading: false,
        entries: result.data || [],
        summary: result.summary || { type_count: 0, total_files: 0, completed_files: 0, pending_files: 0 },
      }));
    } catch (err) {
      setFileTracking((prev) => ({ ...prev, loading: false }));
      setError(err.response?.data?.message || 'Failed to load file tracking for this task.');
    }
  };

  const editFileTrackingRow = (row) => {
    setFileTracking((prev) => ({
      ...prev,
      editingId: row.id,
      fileType: row.file_type || '',
      totalFiles: String(row.total_files ?? ''),
      completedFiles: String(row.completed_files ?? ''),
      remarks: row.remarks || '',
    }));
  };

  const saveFileTracking = async () => {
    if (!taskModal?.id) {
      setError('Open a saved schedule task before adding file tracking.');
      return;
    }

    if (!fileTracking.fileType) {
      setError('Please select a file type from the combo box.');
      return;
    }

    const totalFiles = normalizeNumberInput(fileTracking.totalFiles) || 0;
    const completedFiles = normalizeNumberInput(fileTracking.completedFiles) || 0;

    if (completedFiles > totalFiles) {
      setError('Done files cannot be greater than total files.');
      return;
    }

    setFileTracking((prev) => ({ ...prev, saving: true }));
    setError('');
    setMessage('');

    try {
      await api.post('/schedule/save_task_file_tracking.php', {
        id: fileTracking.editingId,
        procurement_id: procurementId,
        task_id: taskModal.id,
        file_type: fileTracking.fileType,
        total_files: totalFiles,
        completed_files: completedFiles,
        remarks: fileTracking.remarks,
      });
      resetFileTrackingForm();
      setMessage('Task file tracking saved successfully.');
      await loadFileTracking(taskModal);
      await loadSchedule();
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to save task file tracking.');
    } finally {
      setFileTracking((prev) => ({ ...prev, saving: false }));
    }
  };

  const deleteFileTrackingRow = async (row) => {
    if (!taskModal?.id || !row?.id) return;
    const confirmed = window.confirm(`Remove file tracking for ${row.file_type}?`);
    if (!confirmed) return;

    setFileTracking((prev) => ({ ...prev, saving: true }));
    setError('');
    setMessage('');

    try {
      await api.post('/schedule/delete_task_file_tracking.php', {
        id: row.id,
        procurement_id: procurementId,
        task_id: taskModal.id,
      });
      setMessage('Task file tracking removed successfully.');
      await loadFileTracking(taskModal);
      await loadSchedule();
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to remove task file tracking.');
    } finally {
      setFileTracking((prev) => ({ ...prev, saving: false }));
    }
  };

  const loadCommitteeAppointment = async (committeeType, task = taskModal) => {
    if (!procurementId || !committeeType) return;

    const taskPlannedDate = formatDateForInput(task?.planned_date);
    setCommitteeAppointment((prev) => ({
      ...prev,
      loading: true,
      saving: false,
      plannedDate: taskPlannedDate || prev.plannedDate,
      letterDate: taskPlannedDate || prev.letterDate || todayLocal(),
      emailResults: [],
    }));
    setError('');

    try {
      const response = await api.get(
        `/letters/get_committee_appointments.php?procurement_id=${procurementId}&committee_type=${encodeURIComponent(committeeType)}`
      );
      const result = response.data;
      const appointments = Array.isArray(result.appointments) ? result.appointments : [];
      const chairman = appointments.find((appointment) => appointment.committee_position === 'Chairman');
      const memberIds = appointments
        .filter((appointment) => appointment.committee_position !== 'Chairman' && appointment.user_id)
        .map((appointment) => String(appointment.user_id));
      const plannedDate = formatDateForInput(chairman?.appointment_planned_date) || formatDateForInput(result.planned_date) || taskPlannedDate;

      setCommitteeAppointment({
        loading: false,
        saving: false,
        candidates: result.candidates || [],
        appointments,
        chairmanUserId: chairman?.user_id ? String(chairman.user_id) : '',
        memberUserIds: memberIds,
        letterDate: formatDateForInput(chairman?.letter_date) || plannedDate || todayLocal(),
        plannedDate: plannedDate || '',
        role: result.role || '',
        emailResults: [],
        selectionUserId: '',
        selectionPosition: 'Member',
        manualName: '',
        manualEmail: '',
        manualDesignation: '',
        manualPosition: 'Member',
        manualSaving: false,
        emptyMessage: result.empty_message || '',
        blockedCandidates: result.blocked_candidates || [],
        pendingRequestedCandidates: result.pending_requested_candidates || [],
        roleSummary: result.role_summary || null,
        smtpStatus: result.smtp_status || null,
      });
    } catch (err) {
      setCommitteeAppointment((prev) => ({ ...prev, loading: false }));
      setError(err.response?.data?.message || 'Failed to load committee member list.');
    }
  };

  const openTaskModal = (task, index) => {
    const modalTask = { ...task, index };
    setTaskModal(modalTask);
    resetFileTrackingForm();
    loadFileTracking(modalTask);
    const committeeType = getCommitteeTypeForTask(modalTask);
    if (committeeType) {
      loadCommitteeAppointment(committeeType, modalTask);
    } else {
      setCommitteeAppointment(blankCommitteeAppointmentState());
    }
  };

  const getCommitteeCandidateById = (userId) => (
    committeeAppointment.candidates.find((candidate) => String(candidate.id) === String(userId))
  );

  const getSelectedCommitteeRows = () => {
    const rows = [];
    if (committeeAppointment.chairmanUserId) {
      const chairman = getCommitteeCandidateById(committeeAppointment.chairmanUserId);
      if (chairman) rows.push({ ...chairman, committee_position: 'Chairman' });
    }

    committeeAppointment.memberUserIds.forEach((userId) => {
      const member = getCommitteeCandidateById(userId);
      if (member) rows.push({ ...member, committee_position: 'Member' });
    });

    return rows;
  };

  const addCommitteeSelection = () => {
    const selectedUserId = String(committeeAppointment.selectionUserId || '');
    const selectedPosition = committeeAppointment.selectionPosition === 'Chairman' ? 'Chairman' : 'Member';

    if (!selectedUserId) {
      setError('Please select a committee user before clicking Add Selection.');
      return;
    }

    const selectedUser = getCommitteeCandidateById(selectedUserId);
    if (!selectedUser) {
      setError('Selected user is not available for this committee role.');
      return;
    }

    setCommitteeAppointment((prev) => {
      if (selectedPosition === 'Chairman') {
        return {
          ...prev,
          chairmanUserId: selectedUserId,
          memberUserIds: prev.memberUserIds.filter((id) => id !== selectedUserId),
          selectionUserId: '',
          selectionPosition: 'Member',
        };
      }

      if (selectedUserId === String(prev.chairmanUserId)) {
        return prev;
      }

      return {
        ...prev,
        memberUserIds: prev.memberUserIds.includes(selectedUserId)
          ? prev.memberUserIds
          : [...prev.memberUserIds, selectedUserId],
        selectionUserId: '',
        selectionPosition: 'Member',
      };
    });
    setError('');
  };

  const removeCommitteeSelection = (userId) => {
    const idValue = String(userId);
    setCommitteeAppointment((prev) => ({
      ...prev,
      chairmanUserId: idValue === String(prev.chairmanUserId) ? '' : prev.chairmanUserId,
      memberUserIds: prev.memberUserIds.filter((id) => id !== idValue),
    }));
  };


  const handleManualCommitteeFieldChange = (field, value) => {
    setCommitteeAppointment((prev) => ({ ...prev, [field]: value }));
  };

  const addManualCommitteeRecipient = async (committeeType) => {
    if (!committeeType) return;

    const memberName = String(committeeAppointment.manualName || '').trim();
    const memberEmail = String(committeeAppointment.manualEmail || '').trim();
    const memberDesignation = String(committeeAppointment.manualDesignation || '').trim();
    const committeePosition = committeeAppointment.manualPosition === 'Chairman' ? 'Chairman' : 'Member';

    if (!memberName) {
      setError('Please enter the manual committee member name.');
      return;
    }

    if (!memberEmail || !/^\S+@\S+\.\S+$/.test(memberEmail)) {
      setError('Please enter a valid email address for the manual committee member.');
      return;
    }

    const duplicate = (committeeAppointment.appointments || []).some((appointment) => (
      String(appointment.member_email || '').trim().toLowerCase() === memberEmail.toLowerCase()
        && String(appointment.committee_type || '') === committeeType
    ));

    if (duplicate) {
      setError('This email is already added to the saved appointment list for this committee.');
      return;
    }

    setCommitteeAppointment((prev) => ({ ...prev, manualSaving: true }));
    setError('');
    setMessage('');

    try {
      await api.post('/letters/create_committee_letter.php', {
        procurement_id: procurementId,
        committee_type: committeeType,
        committee_position: committeePosition,
        member_name: memberName,
        member_designation: memberDesignation || `${getShortCommitteeName(committeeType)} ${committeePosition}`,
        member_email: memberEmail,
        letter_date: committeeAppointment.letterDate || committeeAppointment.plannedDate || todayLocal(),
        planned_date: committeeAppointment.plannedDate || committeeAppointment.letterDate || taskModal?.planned_date || '',
      });

      setMessage('Manual committee email recipient added. Use Open Gmail in the saved letter table to send the email.');
      setCommitteeAppointment((prev) => ({
        ...prev,
        manualName: '',
        manualEmail: '',
        manualDesignation: '',
        manualPosition: 'Member',
        manualSaving: false,
      }));
      await loadCommitteeAppointment(committeeType, taskModal);
    } catch (err) {
      setCommitteeAppointment((prev) => ({ ...prev, manualSaving: false }));
      setError(err.response?.data?.message || 'Failed to add manual committee email recipient.');
    }
  };

  const saveCommitteeAppointment = async (committeeType, sendEmail = false) => {
    if (!committeeType) return;
    if (!committeeAppointment.chairmanUserId) {
      setError(`Please select the ${getCommitteeDisplayName(committeeType)} chairman.`);
      return;
    }

    setCommitteeAppointment((prev) => ({ ...prev, saving: true, emailResults: [] }));
    setError('');
    setMessage('');

    try {
      const response = await api.post('/letters/save_committee_appointments.php', {
        procurement_id: procurementId,
        committee_type: committeeType,
        chairman_user_id: committeeAppointment.chairmanUserId,
        member_user_ids: committeeAppointment.memberUserIds,
        letter_date: committeeAppointment.letterDate || committeeAppointment.plannedDate || todayLocal(),
        planned_date: committeeAppointment.plannedDate || committeeAppointment.letterDate || taskModal?.planned_date || '',
        send_email: sendEmail,
      });
      const result = response.data;
      const emailResults = result.email_results || [];
      const failedEmails = emailResults.filter((item) => item.email_status === 'failed');
      if (failedEmails.length > 0) {
        setError(`${result.message || 'Some emails failed.'} ${failedEmails.map((item) => `${item.member_email}: ${item.email_error || 'unknown error'}`).join(' | ')}`);
      } else {
        setMessage(result.message || 'Committee appointments saved.');
      }
      setCommitteeAppointment((prev) => ({ ...prev, saving: false, emailResults }));
      await loadCommitteeAppointment(committeeType, taskModal);
    } catch (err) {
      setCommitteeAppointment((prev) => ({ ...prev, saving: false }));
      setError(err.response?.data?.message || 'Failed to save committee appointments.');
    }
  };

  const sendExistingCommitteeLetter = async (letterId, committeeType) => {
    if (!letterId) return;
    setCommitteeAppointment((prev) => ({ ...prev, saving: true, emailResults: [] }));
    setError('');
    setMessage('');

    try {
      const response = await api.post('/letters/send_committee_letter_email.php', { id: letterId });
      if (response.data?.email_status === 'failed') {
        setError(response.data?.message || response.data?.email_error || 'Committee appointment email failed.');
      } else {
        setMessage(response.data?.message || 'Committee appointment email sent.');
      }
      await loadCommitteeAppointment(committeeType, taskModal);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to send committee appointment email.');
    } finally {
      setCommitteeAppointment((prev) => ({ ...prev, saving: false }));
    }
  };

  const sendAllExistingCommitteeLetters = async (committeeType) => {
    if (!committeeType) return;
    const letters = committeeAppointment.appointments || [];
    if (letters.length === 0) {
      setError('No saved appointment letters found. First select chairman/member and save appointment.');
      return;
    }

    setCommitteeAppointment((prev) => ({ ...prev, saving: true, emailResults: [] }));
    setError('');
    setMessage('');

    const failed = [];
    let sentCount = 0;

    try {
      for (const letter of letters) {
        const response = await api.post('/letters/send_committee_letter_email.php', { id: letter.id });
        if (response.data?.email_status === 'failed' || response.data?.success === false) {
          failed.push(`${letter.member_email || letter.member_name}: ${response.data?.email_error || response.data?.message || 'email failed'}`);
        } else {
          sentCount += 1;
        }
      }

      await loadCommitteeAppointment(committeeType, taskModal);
      if (failed.length > 0) {
        setError(`Email completed with ${failed.length} failed and ${sentCount} sent. ${failed.join(' | ')}`);
      } else {
        setMessage(`Email sent successfully to ${sentCount} committee member(s).`);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to send appointment emails.');
    } finally {
      setCommitteeAppointment((prev) => ({ ...prev, saving: false }));
    }
  };

  const buildCommitteeEmailContent = (appointment) => {
    const committeeType = getCommitteeTypeForTask(taskModal);
    const committeeName = getCommitteeDisplayName(committeeType);
    const reference = procurement?.tender_number || procurement?.procurement_id || procurementId || 'Procurement';
    const subject = `PMTS Appointment Letter - ${getShortCommitteeName(committeeType)} - ${reference}`;
    const body = [
      `Dear ${appointment.member_name || 'Committee Member'},`,
      '',
      `You have been appointed as ${appointment.committee_position || 'Member'} of the ${committeeName}.`,
      '',
      `Procurement: ${procurement?.title || 'Procurement'}`,
      reference ? `Reference: ${reference}` : '',
      `Appointment Letter Date: ${appointment.letter_date || committeeAppointment.letterDate || todayLocal()}`,
      appointment.appointment_planned_date ? `Planned Appointment Date: ${appointment.appointment_planned_date}` : '',
      '',
      appointment.letter_body || 'Please carry out the assigned duties according to the approved procurement time schedule.',
      '',
      'Regards,',
      currentUser?.full_name || currentUser?.name || 'Procurement Management Tracking System',
      currentUser?.email ? `Procurement Officer Email: ${currentUser.email}` : '',
    ].filter((line) => line !== '').join('\n');

    return { subject, body };
  };

  const getProcurementOfficerGmailAddress = () => String(currentUser?.email || currentUser?.user_email || '').trim();

  const buildCommitteeGmailUrl = (appointment) => {
    const { subject, body } = buildCommitteeEmailContent(appointment);
    const procurementOfficerEmail = getProcurementOfficerGmailAddress();
    const params = new URLSearchParams({
      authuser: procurementOfficerEmail,
      view: 'cm',
      fs: '1',
      tf: '1',
      to: appointment.member_email || '',
      su: subject,
      body,
    });

    return `https://mail.google.com/mail/?${params.toString()}`;
  };

  const openManualMailClient = async (appointment) => {
    if (!appointment?.member_email) {
      setError('This saved letter does not have a member email address.');
      return;
    }

    const procurementOfficerEmail = getProcurementOfficerGmailAddress();
    if (!procurementOfficerEmail) {
      setError('Procurement Officer email is missing from the logged-in PMTS account. Please log in with the Procurement Officer account first.');
      return;
    }

    if (currentUser?.role !== 'procurement_officer') {
      setError(`You are logged in as ${currentUser?.role || 'another role'}. To send from the Procurement Officer Gmail, log in to PMTS using the Procurement Officer account first.`);
      return;
    }

    const gmailUrl = buildCommitteeGmailUrl(appointment);
    const gmailWindow = window.open(gmailUrl, '_blank', 'noopener,noreferrer');

    if (!gmailWindow) {
      setError('Browser blocked the Gmail popup. Please allow popups for PMTS and click Open Procurement Officer Gmail again.');
      return;
    }

    setMessage(`Opening Procurement Officer Gmail (${procurementOfficerEmail}) with recipient, subject, and body filled. Click Send in Gmail, then click Mark Sent in PMTS.`);

    try {
      await api.post('/letters/mark_committee_letter_manual_email.php', {
        id: appointment.id,
        action: 'opened_mail_client',
      });
      await loadCommitteeAppointment(getCommitteeTypeForTask(taskModal), taskModal);
    } catch (err) {
      setError(err.response?.data?.message || 'Gmail was opened, but PMTS could not update the manual email status.');
    }
  };

  const markManualEmailSent = async (appointment) => {
    if (!appointment?.id) return;
    const confirmed = window.confirm('Mark this appointment letter as sent manually? Use this only after you clicked Send in Gmail/Outlook.');
    if (!confirmed) return;

    setCommitteeAppointment((prev) => ({ ...prev, saving: true }));
    setError('');
    setMessage('');

    try {
      await api.post('/letters/mark_committee_letter_manual_email.php', {
        id: appointment.id,
        action: 'sent_manually',
      });
      setMessage('Manual email status saved as sent manually.');
      await loadCommitteeAppointment(getCommitteeTypeForTask(taskModal), taskModal);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to mark manual email as sent.');
    } finally {
      setCommitteeAppointment((prev) => ({ ...prev, saving: false }));
    }
  };

  const printSchedule = () => {
    window.print();
  };

  const openPrintableSchedule = () => {
    const token = localStorage.getItem('token') || '';
    const url = `${API_BASE_URL}/schedule/print_ncb_schedule.php?procurement_id=${procurementId}&token=${encodeURIComponent(token)}`;
    window.open(url, '_blank', 'noopener,noreferrer');
  };

  const downloadCSV = () => {
    const headers = ['No', 'Task', 'Responsible Role / Committee', 'Planned Date', 'Actual Date', 'Status', 'File Tracking', 'Remarks'];
    const rows = tasks.map((task, index) => [
      index + 1,
      task.task_name,
      task.responsible_role,
      task.planned_date,
      task.actual_date,
      task.status,
      fileTrackingSummaryText(task.file_tracking_summary),
      task.remarks,
    ]);

    const csvContent = [headers, ...rows, [], ['Payment Date', paymentDate || procurement?.payment_date || '']]
      .map((row) => row.map((cell) => `"${String(cell ?? '').replace(/"/g, '""')}"`).join(','))
      .join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `Procurement_Time_Schedule_${procurement?.procurement_id || procurementId}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  };

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="procurement-solid-header sticky-procurement-header print-hide">
          <button className="btn btn-outline btn-sm" type="button" onClick={() => navigate(isITAdmin ? '/admin-settings' : '/officer-management')}>
            <Icon name="back" size={16} /> Back
          </button>
          <div className="procurement-solid-header-text">
            <div className="page-title" style={{ marginBottom: 0 }}>
              {procurement?.title || 'Procurement Time Schedule'}
            </div>
            <div className="page-subtitle" style={{ marginBottom: 0 }}>
              Tender: {procurement?.tender_number || '—'} | Procurement ID: {procurement?.procurement_id || procurementId || '—'}
            </div>
          </div>
        </div>

        <div className="print-only" style={{ display: 'none' }}>
          <h2>{procurement?.title || 'Procurement Time Schedule'}</h2>
          <p>Tender: {procurement?.tender_number || '—'} | Procurement ID: {procurement?.procurement_id || procurementId || '—'}</p>
        </div>

        <div className="page-subtitle print-hide">
          This table uses the same 15 tasks from the NCB time schedule PHP file. Click a task name to read it clearly.
        </div>

        {error && <div className="alert alert-danger print-hide">{error}</div>}
        {message && <div className="alert alert-success print-hide">{message}</div>}

        <div className="form-card schedule-page-card" style={{ maxWidth: '1320px' }}>
          <div className="form-section-title" style={{ marginTop: 0 }}>Procurement Details</div>

          <div className="form-row three-col">
            <div className="form-group">
              <label className="form-label">Procurement ID</label>
              <input className="form-input" value={procurement?.procurement_id || procurementId || ''} readOnly />
            </div>
            <div className="form-group">
              <label className="form-label">Procurement Type</label>
              <input className="form-input" value={procurement?.procurement_type || ''} readOnly />
            </div>
            <div className="form-group">
              <label className="form-label">Progress</label>
              <input className="form-input" value={`${stats.progress || 0}% Completed`} readOnly />
            </div>
          </div>

          <div className="form-row">
            <div className="form-group">
              <label className="form-label">Current Location</label>
              <input className="form-input" value={procurement?.current_stage_label || procurement?.current_location || stats.current_location || '—'} readOnly />
            </div>
            <div className="form-group">
              <label className="form-label">Current Schedule Task</label>
              <input className="form-input" value={procurement?.current_task_label || stats.current_task?.task_label || '—'} readOnly />
            </div>
          </div>

          <div className="form-row">
            <div className="form-group">
              <label className="form-label">Title</label>
              <input className="form-input" value={procurement?.title || ''} readOnly />
            </div>
            <div className="form-group">
              <label className="form-label">File Name</label>
              <div className={`priority-file-name priority-${procurement?.priority || 'medium'}`}>
                {procurement?.file_name || procurement?.title || '—'}
              </div>
            </div>
          </div>

          <div className="form-row print-hide">
            <div className="form-group">
              <label className="form-label">Schedule Category</label>
              {isITAdmin ? (
                <select
                  className="form-select"
                  value={selectedCategory}
                  onChange={(e) => setSelectedCategory(e.target.value)}
                >
                  <option value="">— Select category —</option>
                  {selectedCategory && !categories.includes(selectedCategory) && (
                    <option value={selectedCategory}>{selectedCategory}</option>
                  )}
                  {categories.map((category) => (
                    <option key={category} value={category}>
                      {category}
                    </option>
                  ))}
                </select>
              ) : (
                <input className="form-input" value={procurement?.category || ''} readOnly />
              )}
            </div>

            {isITAdmin && (
              <div className="form-group">
                <label className="form-label">Add New Category</label>
                <div style={{ display: 'flex', gap: '8px' }}>
                  <input
                    className="form-input"
                    placeholder="Enter category name"
                    value={newCategory}
                    onChange={(e) => setNewCategory(e.target.value)}
                  />
                  <button className="btn btn-outline" type="button" onClick={addCategory} disabled={categorySaving}>
                    + Add
                  </button>
                </div>
              </div>
            )}
          </div>

          {isITAdmin && (
            <div className="print-hide" style={{ marginTop: '-8px', marginBottom: '16px' }}>
              <button className="btn btn-primary" type="button" onClick={saveScheduleCategory} disabled={categorySaving}>
                {categorySaving ? 'Saving Category...' : ' Save Schedule Category'}
              </button>
              <span className="form-help" style={{ marginLeft: '10px' }}>
                Only IT Admin can add new categories and assign them to this schedule.
              </span>
            </div>
          )}

          <div className="print-only" style={{ display: 'none' }}>
            <strong>Category:</strong> {procurement?.category || selectedCategory || ''}
          </div>

          <div className="form-row three-col">
            <div className="form-group">
              <label className="form-label">Total Tasks</label>
              <input className="form-input" value={stats.total || tasks.length} readOnly />
            </div>
            <div className="form-group">
              <label className="form-label">Applicable Tasks</label>
              <input className="form-input" value={stats.applicable ?? Math.max((stats.total || tasks.length) - (stats.skipped || 0), 0)} readOnly />
            </div>
            <div className="form-group">
              <label className="form-label">Skipped / Not Necessary</label>
              <input className="form-input" value={stats.skipped || 0} readOnly />
            </div>
          </div>

          <div className="form-row three-col">
            <div className="form-group">
              <label className="form-label">Completed</label>
              <input className="form-input" value={stats.completed || 0} readOnly />
            </div>
            <div className="form-group">
              <label className="form-label">Delayed</label>
              <input className="form-input" value={stats.delayed || 0} readOnly />
            </div>
            <div className="form-group">
              <label className="form-label">Tracked Files</label>
              <input className="form-input" value={`${stats.tracked_files || 0} total / ${stats.completed_files || 0} done`} readOnly />
            </div>
          </div>


          <div className="form-section-title">Schedule Table</div>
          <p className="form-help print-hide">
            Dates are not erased when a task is skipped. Allowed delay days come from IT Admin System Settings when the schedule is created. Actual dates are entered manually by the relevant officer and are never filled from another task's planned date.
          </p>

          {loading ? (
            <p className="form-help">Loading schedule...</p>
          ) : (
            <div className="schedule-table-wrapper">
              <table className="schedule-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Activity / Milestone</th>
                    <th>Responsible Role / Committee</th>
                    <th>Planned Date</th>
                    <th>Allowed Delay</th>
                    <th>Actual Date</th>
                    <th>Status</th>
                    <th>Delay Alert</th>
                    <th>File Tracking</th>
                    <th>Remarks / Skip Reason</th>
                    <th className="print-hide">Action</th>
                  </tr>
                </thead>
                <tbody>
                  {tasks.map((task, index) => {
                    const isSkipped = task.status === 'skipped';
                    const delayInfo = getClientDelayInfo(task) || task.delay_info;
                    return (
                      <tr key={task.id || index} className={isSkipped ? 'is-skipped-row' : ''}>
                        <td>{index + 1}</td>
                        <td className="task-name">
                          <button type="button" className="task-link-button" onClick={() => openTaskModal(task, index)}>
                            {task.task_name}
                          </button>
                        </td>
                        <td>
                          <input type="text" value={task.responsible_role || ''} readOnly />
                        </td>
                        <td>
                          <input
                            type="date"
                            value={task.planned_date}
                            disabled={isSkipped}
                            onChange={(e) => handleTaskChange(index, 'planned_date', e.target.value)}
                          />
                        </td>
                        <td>
                          <span className="badge badge-info">{normalizeDelayDays(task.allowed_delay_days)} day(s)</span>
                          <div className="text-muted text-xs" style={{ marginTop: '4px' }}>
                            Deadline: {task.planned_date ? addDays(task.planned_date, task.allowed_delay_days) : '—'}
                          </div>
                        </td>
                        <td>
                          <input
                            type="date"
                            value={task.actual_date}
                            disabled={isSkipped}
                            onChange={(e) => handleTaskChange(index, 'actual_date', e.target.value)}
                          />
                        </td>
                        <td>
                          <select
                            className="form-select"
                            value={task.status}
                            onChange={(e) => handleTaskChange(index, 'status', e.target.value)}
                          >
                            {STATUS_OPTIONS.map((status) => (
                              <option key={status.value} value={status.value}>
                                {status.label}
                              </option>
                            ))}
                          </select>
                        </td>
                        <td>
                          {delayInfo ? (
                            <div>
                              <span className={`badge ${delayInfo.alert_color === 'red' ? 'badge-danger' : 'badge-warning'}`}>
                                {delayInfo.alert_color === 'red' ? 'Red' : 'Yellow'} - {delayInfo.days_late} day(s)
                              </span>
                              <div className="text-muted text-xs" style={{ marginTop: '4px' }}>{delayInfo.description}</div>
                            </div>
                          ) : (
                            <span className="badge badge-gray">No Delay</span>
                          )}
                        </td>
                        <td>
                          <button type="button" className="task-file-summary-button" onClick={() => openTaskModal(task, index)}>
                            <strong>{task.file_tracking_summary?.total_files || 0} file(s)</strong>
                            <span>{task.file_tracking_summary?.completed_files || 0} done / {task.file_tracking_summary?.pending_files || 0} pending</span>
                            <small>{task.file_tracking_summary?.type_count || 0} type(s)</small>
                          </button>
                        </td>
                        <td>
                          <input
                            type="text"
                            placeholder={isSkipped ? 'Skip reason...' : 'Remarks...'}
                            value={task.remarks}
                            onChange={(e) => handleTaskChange(index, 'remarks', e.target.value)}
                          />
                        </td>
                        <td className="print-hide">
                          <div className="schedule-row-actions">
                            <button className="action-btn edit" type="button" onClick={() => setCurrentScheduleTaskForRow(task, index)} disabled={saving || !task.id}>
                              Set Current
                            </button>
                            {isSkipped ? (
                              <button className="action-btn view" type="button" onClick={() => unskipTask(index)} disabled={saving}>
                                Use Task
                              </button>
                            ) : (
                              <button className="action-btn reject" type="button" onClick={() => skipTask(index)} disabled={saving}>
                                Skip
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}

          <div className="payment-date-section" style={{ marginTop: '18px', padding: '14px', border: '1px solid var(--border)', borderRadius: '12px', background: '#F8FAFC' }}>
            <div className="form-section-title" style={{ marginTop: 0 }}>Payment Date</div>
            <div className="form-row" style={{ alignItems: 'end' }}>
              <div className="form-group">
                <label className="form-label">Payment Date</label>
                <input
                  className="form-input"
                  type="date"
                  value={paymentDate}
                  onChange={(e) => setPaymentDate(e.target.value)}
                />
              </div>
              <div className="form-group print-hide">
                <button className="btn btn-primary" type="button" onClick={savePaymentDate} disabled={saving || loading}>
                   Save Payment Date
                </button>
              </div>
            </div>
            <p className="form-help print-hide" style={{ marginBottom: 0 }}>
              This payment date is saved with the procurement and appears at the bottom of the printable schedule.
            </p>
          </div>

          <div className="form-section-title print-hide">Add Extra Schedule Task</div>
          <div className="form-row print-hide">
            <div className="form-group">
              <input
                className="form-input"
                placeholder="Enter extra schedule task"
                value={newTask}
                onChange={(e) => setNewTask(e.target.value)}
              />
            </div>
            <div className="form-group">
              <button className="btn btn-outline" type="button" onClick={addNewTask} disabled={saving}>
                Add Task
              </button>
            </div>
          </div>

          <div className="form-footer print-hide">
            <button className="btn btn-outline" type="button" onClick={resetToStandardTasks} disabled={saving}>
              Reset Standard Tasks
            </button>
            <button className="btn btn-outline" type="button" onClick={printSchedule}>
              <Icon name="print" size={16} /> Print Page
            </button>
            <button className="btn btn-outline" type="button" onClick={openPrintableSchedule}>
              <Icon name="print" size={16} /> Print Format
            </button>
            <button className="btn btn-outline" type="button" onClick={downloadCSV}>
              <Icon name="download" size={16} /> Download CSV
            </button>
            <button className="btn btn-primary" type="button" onClick={saveAllTasks} disabled={saving || loading}>
              {saving ? 'Saving...' : 'Save Schedule'}
            </button>
          </div>
        </div>

        {taskModal && (
          <div className="task-modal-backdrop print-hide" onClick={() => setTaskModal(null)}>
            <div className="task-modal-card committee-task-modal-card" onClick={(event) => event.stopPropagation()}>
              <div className="section-header dashboard-card-header">
                <div>
                  <div className="section-title">Task {taskModal.index + 1}</div>
                  <p className="text-muted text-sm">{taskModal.responsible_role || 'Responsible role not set'}</p>
                </div>
                <button className="btn btn-outline btn-sm" type="button" onClick={() => setTaskModal(null)}>Close</button>
              </div>
              <p className="task-modal-task-name">{taskModal.task_name}</p>
              <div className="task-modal-grid">
                <div><span>Planned Date</span><strong>{taskModal.planned_date || '—'}</strong></div>
                <div><span>Allowed Delay</span><strong>{normalizeDelayDays(taskModal.allowed_delay_days)} day(s)</strong></div>
                <div><span>Allowed Deadline</span><strong>{taskModal.planned_date ? addDays(taskModal.planned_date, taskModal.allowed_delay_days) : '—'}</strong></div>
                <div><span>Actual Date</span><strong>{taskModal.actual_date || '—'}</strong></div>
                <div><span>Status</span><strong>{taskModal.status || 'pending'}</strong></div>
                <div><span>Remarks</span><strong>{taskModal.remarks || '—'}</strong></div>
              </div>

              <div className="task-file-tracking-panel">
                <div className="section-header dashboard-card-header">
                  <div>
                    <div className="section-title">Task File Tracking</div>
                    <p className="text-muted text-sm">Track how many files are inside this NCB schedule task by file type.</p>
                  </div>
                </div>

                <div className="task-file-summary-cards">
                  <div><span>Total Files</span><strong>{fileTracking.summary?.total_files || 0}</strong></div>
                  <div><span>Done Files</span><strong>{fileTracking.summary?.completed_files || 0}</strong></div>
                  <div><span>Pending Files</span><strong>{fileTracking.summary?.pending_files || 0}</strong></div>
                  <div><span>Types</span><strong>{fileTracking.summary?.type_count || 0}</strong></div>
                </div>

                <div className="task-file-tracking-form">
                  <div className="form-group">
                    <label className="form-label">File Type</label>
                    <select
                      className="form-select"
                      value={fileTracking.fileType}
                      onChange={(e) => setFileTracking((prev) => ({ ...prev, fileType: e.target.value }))}
                    >
                      <option value="">— Select type —</option>
                      {FILE_TYPE_OPTIONS.map((type) => (
                        <option key={type} value={type}>{type}</option>
                      ))}
                    </select>
                  </div>
                  <div className="form-group">
                    <label className="form-label">How Many Files</label>
                    <input
                      className="form-input"
                      type="number"
                      min="0"
                      value={fileTracking.totalFiles}
                      onChange={(e) => setFileTracking((prev) => ({ ...prev, totalFiles: e.target.value }))}
                      placeholder="Total"
                    />
                  </div>
                  <div className="form-group">
                    <label className="form-label">Done Files</label>
                    <input
                      className="form-input"
                      type="number"
                      min="0"
                      value={fileTracking.completedFiles}
                      onChange={(e) => setFileTracking((prev) => ({ ...prev, completedFiles: e.target.value }))}
                      placeholder="Done"
                    />
                  </div>
                  <div className="form-group">
                    <label className="form-label">Remarks</label>
                    <input
                      className="form-input"
                      value={fileTracking.remarks}
                      onChange={(e) => setFileTracking((prev) => ({ ...prev, remarks: e.target.value }))}
                      placeholder="Optional note"
                    />
                  </div>
                  <div className="form-group task-file-actions">
                    <label className="form-label">Action</label>
                    <button className="btn btn-primary" type="button" onClick={saveFileTracking} disabled={fileTracking.saving}>
                      {fileTracking.saving ? 'Saving...' : fileTracking.editingId ? 'Update' : 'Add / Save'}
                    </button>
                    {fileTracking.editingId && (
                      <button className="btn btn-outline" type="button" onClick={resetFileTrackingForm} disabled={fileTracking.saving}>
                        Cancel
                      </button>
                    )}
                  </div>
                </div>

                {fileTracking.loading ? (
                  <p className="form-help">Loading file tracking...</p>
                ) : fileTracking.entries.length === 0 ? (
                  <div className="alert alert-info">No files tracked yet for this task. Select a type, enter the count, and click Add / Save.</div>
                ) : (
                  <table className="data-table mini-committee-table task-file-tracking-table">
                    <thead>
                      <tr>
                        <th>Type</th>
                        <th>Total</th>
                        <th>Done</th>
                        <th>Pending</th>
                        <th>Remarks</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      {fileTracking.entries.map((row) => (
                        <tr key={row.id}>
                          <td>{row.file_type}</td>
                          <td>{row.total_files}</td>
                          <td>{row.completed_files}</td>
                          <td>{row.pending_files}</td>
                          <td>{row.remarks || '—'}</td>
                          <td>
                            <button className="action-btn view" type="button" onClick={() => editFileTrackingRow(row)} disabled={fileTracking.saving}>Edit</button>
                            <button className="action-btn reject" type="button" onClick={() => deleteFileTrackingRow(row)} disabled={fileTracking.saving}>Delete</button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </div>

              {getCommitteeTypeForTask(taskModal) && (
                <div className="committee-appointment-panel">
                  <div className="section-header dashboard-card-header">
                    <div>
                      <div className="section-title">{getCommitteeDisplayName(getCommitteeTypeForTask(taskModal))} Appointment</div>
                      <p className="text-muted text-sm">
                        Select the chairman and members here, then send appointment letters directly from this NCB Time Schedule task. Candidates are loaded by users.role and cross-committee selections are blocked.
                      </p>
                    </div>
                  </div>

                  {!committeeAppointment.loading && (
                    <div className="committee-email-quickbar">
                      <button
                        className="btn btn-success committee-email-main-btn"
                        type="button"
                        disabled={committeeAppointment.saving || committeeAppointment.appointments.length === 0}
                        onClick={() => sendAllExistingCommitteeLetters(getCommitteeTypeForTask(taskModal))}
                      >
                        {committeeAppointment.saving ? 'Sending Emails...' : 'Send Email to Saved Members'}
                      </button>
                      <span className="form-help">
                        Email button is always visible here. If it is disabled, save the appointment first.
                      </span>
                    </div>
                  )}

                  {committeeAppointment.loading ? (
                    <p className="form-help">Loading committee users...</p>
                  ) : (
                    <>
                      {committeeAppointment.candidates.length === 0 && (
                        <div className="alert alert-warning">
                          <strong>No eligible active {getCommitteeTypeForTask(taskModal) === 'BEC' ? 'BEC Member' : 'Specification Committee'} users found.</strong>
                          <div style={{ marginTop: '6px' }}>
                            {committeeAppointment.emptyMessage || 'Add/activate users with the correct role in User Management, or remove them from the opposite committee for this procurement.'}
                          </div>
                          {committeeAppointment.roleSummary && (
                            <div className="text-sm" style={{ marginTop: '8px' }}>
                              Active correct-role users: {committeeAppointment.roleSummary.active_required_role_count || 0} •
                              Blocked by opposite committee: {committeeAppointment.roleSummary.blocked_by_opposite_committee_count || 0} •
                              Pending/requested users: {committeeAppointment.roleSummary.pending_requested_role_count || 0}
                            </div>
                          )}
                          {committeeAppointment.pendingRequestedCandidates.length > 0 && (
                            <div className="text-sm" style={{ marginTop: '8px' }}>
                              Pending/requested: {committeeAppointment.pendingRequestedCandidates.map((user) => user.full_name || user.email).join(', ')}
                            </div>
                          )}
                          {committeeAppointment.blockedCandidates.length > 0 && (
                            <div className="text-sm" style={{ marginTop: '8px' }}>
                              Blocked: {committeeAppointment.blockedCandidates.map((user) => user.full_name || user.email).join(', ')}
                            </div>
                          )}
                        </div>
                      )}

                      <div className="committee-selection-box">
                        <div className="form-row committee-selection-row">
                          <div className="form-group">
                            <label className="form-label">Appointment Letter Date</label>
                            <input
                              className="form-input"
                              type="date"
                              value={committeeAppointment.letterDate}
                              onChange={(event) => setCommitteeAppointment((prev) => ({ ...prev, letterDate: event.target.value, plannedDate: event.target.value }))}
                            />
                            <small className="form-help">Auto-filled from this task planned date: {committeeAppointment.plannedDate || 'not set'}. You can change it before saving.</small>
                          </div>
                          <div className="form-group">
                            <label className="form-label">Committee User</label>
                            <select
                              className="form-select"
                              value={committeeAppointment.selectionUserId}
                              onChange={(event) => setCommitteeAppointment((prev) => ({ ...prev, selectionUserId: event.target.value }))}
                              disabled={committeeAppointment.candidates.length === 0}
                            >
                              <option value="">— Select user —</option>
                              {committeeAppointment.candidates.map((candidate) => (
                                <option key={candidate.id} value={candidate.id}>
                                  {candidate.full_name} ({candidate.email})
                                </option>
                              ))}
                            </select>
                          </div>
                          <div className="form-group">
                            <label className="form-label">Committee Position</label>
                            <select
                              className="form-select"
                              value={committeeAppointment.selectionPosition}
                              onChange={(event) => setCommitteeAppointment((prev) => ({ ...prev, selectionPosition: event.target.value }))}
                              disabled={committeeAppointment.candidates.length === 0}
                            >
                              <option value="Chairman">Chairman</option>
                              <option value="Member">Member</option>
                            </select>
                          </div>
                          <div className="form-group committee-add-selection-group">
                            <label className="form-label">Action</label>
                            <button
                              className="btn btn-outline"
                              type="button"
                              onClick={addCommitteeSelection}
                              disabled={committeeAppointment.candidates.length === 0}
                            >
                              + Add Selection
                            </button>
                          </div>
                        </div>

                        <p className="form-help">
                          Showing only active users with role <strong>{committeeAppointment.role || (getCommitteeTypeForTask(taskModal) === 'BEC' ? 'bec_member' : 'specification_committee')}</strong>.
                          BEC users are not shown in Specification selection, and Specification users are not shown in BEC selection.
                        </p>
                      </div>

                      <div className="committee-selection-box committee-manual-recipient-box">
                        <div className="section-title" style={{ fontSize: '1rem' }}>Add Email Recipient Manually</div>
                        <p className="form-help">
                          Use this when a BEC/Specification committee member is not registered in PMTS. The person will be saved only for this appointment letter and email sending.
                        </p>
                        <div className="form-row committee-selection-row">
                          <div className="form-group">
                            <label className="form-label">Full Name</label>
                            <input
                              className="form-input"
                              type="text"
                              value={committeeAppointment.manualName}
                              onChange={(event) => handleManualCommitteeFieldChange('manualName', event.target.value)}
                              placeholder="Enter member full name"
                            />
                          </div>
                          <div className="form-group">
                            <label className="form-label">Email</label>
                            <input
                              className="form-input"
                              type="email"
                              value={committeeAppointment.manualEmail}
                              onChange={(event) => handleManualCommitteeFieldChange('manualEmail', event.target.value)}
                              placeholder="member@example.com"
                            />
                          </div>
                          <div className="form-group">
                            <label className="form-label">Designation</label>
                            <input
                              className="form-input"
                              type="text"
                              value={committeeAppointment.manualDesignation}
                              onChange={(event) => handleManualCommitteeFieldChange('manualDesignation', event.target.value)}
                              placeholder="Designation / Department"
                            />
                          </div>
                          <div className="form-group">
                            <label className="form-label">Position</label>
                            <select
                              className="form-select"
                              value={committeeAppointment.manualPosition}
                              onChange={(event) => handleManualCommitteeFieldChange('manualPosition', event.target.value)}
                            >
                              <option value="Chairman">Chairman</option>
                              <option value="Member">Member</option>
                            </select>
                          </div>
                          <div className="form-group committee-add-selection-group">
                            <label className="form-label">Action</label>
                            <button
                              className="btn btn-outline"
                              type="button"
                              disabled={committeeAppointment.manualSaving || committeeAppointment.saving}
                              onClick={() => addManualCommitteeRecipient(getCommitteeTypeForTask(taskModal))}
                            >
                              {committeeAppointment.manualSaving ? 'Adding...' : '+ Add Manual Recipient'}
                            </button>
                          </div>
                        </div>
                      </div>

                      <div className="committee-selected-box">
                        <div className="form-label">Selected Committee Members</div>
                        {getSelectedCommitteeRows().length === 0 ? (
                          <p className="form-help">No chairman or members selected yet. Select a user, choose Chairman or Member, then click Add Selection.</p>
                        ) : (
                          <table className="data-table mini-committee-table committee-selected-table">
                            <thead>
                              <tr>
                                <th>Position</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role Type</th>
                                <th>Action</th>
                              </tr>
                            </thead>
                            <tbody>
                              {getSelectedCommitteeRows().map((selectedUser) => (
                                <tr key={`${selectedUser.committee_position}-${selectedUser.id}`}>
                                  <td><span className={selectedUser.committee_position === 'Chairman' ? 'badge badge-warning' : 'badge badge-gray'}>{selectedUser.committee_position}</span></td>
                                  <td>{selectedUser.full_name}</td>
                                  <td>{selectedUser.email}</td>
                                  <td>{selectedUser.role}</td>
                                  <td>
                                    <button
                                      className="action-btn reject"
                                      type="button"
                                      onClick={() => removeCommitteeSelection(selectedUser.id)}
                                    >
                                      Remove
                                    </button>
                                  </td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        )}
                      </div>

                      <div className="form-footer committee-mail-actions" style={{ justifyContent: 'flex-start' }}>
                        <button
                          className="btn btn-outline"
                          type="button"
                          disabled={committeeAppointment.saving}
                          onClick={() => saveCommitteeAppointment(getCommitteeTypeForTask(taskModal), false)}
                        >
                          {committeeAppointment.saving ? 'Saving...' : 'Save Appointment'}
                        </button>
                      </div>

                      <p className="form-help">
                        Save Appointment creates/updates appointment letters. Email sending is handled from the saved letter table after the appointment is saved. Actual date is still entered manually by the relevant officer in the schedule table.
                      </p>

                      <div className="alert alert-info" style={{ marginTop: 10 }}>
                        <strong>No App Password option:</strong> use the <strong>Open Procurement Officer Gmail</strong> button in the saved letter table. It opens the Procurement Officer Gmail compose screen in the browser with recipient, subject, and body filled, when the Procurement Officer Gmail account is already signed in. You must click Send manually, then click <strong>Mark Sent</strong> to save the status in PMTS.
                      </div>

                      

                      {committeeAppointment.appointments.length > 0 && (
                        <div className="committee-existing-appointments">
                          <div className="committee-saved-header">
                            <div className="form-label">Saved Appointment Letters</div>
                            <button
                              className="btn btn-success committee-email-main-btn"
                              type="button"
                              disabled={committeeAppointment.saving}
                              onClick={() => sendAllExistingCommitteeLetters(getCommitteeTypeForTask(taskModal))}
                            >
                              {committeeAppointment.saving ? 'Sending Emails...' : 'Email All Saved Letters'}
                            </button>
                          </div>
                          <table className="data-table mini-committee-table">
                            <thead>
                              <tr>
                                <th>Position</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Planned Date</th>
                                <th>Mail Status</th>
                                <th>Actions</th>
                              </tr>
                            </thead>
                            <tbody>
                              {committeeAppointment.appointments.map((appointment) => (
                                <tr key={appointment.id}>
                                  <td>{appointment.committee_position || 'Member'}</td>
                                  <td>{appointment.member_name}</td>
                                  <td>{appointment.member_email}</td>
                                  <td>{appointment.appointment_planned_date || appointment.letter_date || '—'}</td>
                                  <td title={appointment.email_error || ''}>{statusText(appointment.email_status || (appointment.sent_at ? 'sent' : 'not_sent'))}</td>
                                  <td>
                                    <a
                                      //className="action-btn view"
                                      className="btn btn-outline committee-row-email-btn"
                                      href={`${API_BASE_URL}/letters/download_committee_letter.php?id=${appointment.id}`}
                                      target="_blank"
                                      rel="noreferrer"
                                    >
                                      Print
                                    </a>
                                    <button
                                      //className="btn btn-success committee-row-email-btn"
                                      //type="button"
                                      //disabled={committeeAppointment.saving}
                                      //onClick={() => sendExistingCommitteeLetter(appointment.id, getCommitteeTypeForTask(taskModal))}
                                    >
                                      
                                    </button><br></br>
                                    <br></br>
                                    <button
                                      className="btn btn-outline committee-row-email-btn"
                                      type="button"
                                      disabled={committeeAppointment.saving}
                                      onClick={() => openManualMailClient(appointment)}
                                      title="No App Password needed. Opens Procurement Officer Gmail compose with recipient, subject, and body filled."
                                    >
                                      Open Gmail
                                    </button>
                                    <br></br>
                                    <br></br>
                                    <button
                                      className="btn btn-outline committee-row-email-btn"
                                      type="button"
                                      disabled={committeeAppointment.saving}
                                      onClick={() => markManualEmailSent(appointment)}
                                    >
                                      Mark Sent
                                    </button>
                                  </td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                      )}
                    </>
                  )}
                </div>
              )}
            </div>
          </div>
        )}
      </div>
    </Layout>
  );
}
