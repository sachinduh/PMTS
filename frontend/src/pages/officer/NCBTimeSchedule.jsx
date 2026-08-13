import { useEffect, useState } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import Layout from '../../components/Layout';
import Icon from '../../components/Icon';
import api, { API_BASE_URL } from '../../api/api';
import { getUser } from '../../utils/auth';
import '../../styles/forms.css';
import '../../styles/tables.css';

const DEFAULT_SCHEDULE_TASKS = [
  { task_name: 'Receive procurement request and enter basic procurement details', responsible_role: 'Procurement Officer' },
  { task_name: 'Select procurement type and open time schedule', responsible_role: 'Procurement Officer' },
  { task_name: 'Prepare procurement plan / initial procurement file', responsible_role: 'Procurement Officer' },
  { task_name: 'Appoint Specification Preparation Committee', responsible_role: 'Director / Procurement Branch' },
  { task_name: 'Prepare technical specification', responsible_role: 'Specification Preparation Committee' },
  { task_name: 'Submit finalized specification to Procurement Branch', responsible_role: 'Specification Preparation Committee' },
  { task_name: 'Appoint Technical Evaluation Committee (TEC)', responsible_role: 'Director / Procurement Branch' },
  { task_name: 'Appoint Bid Evaluation Committee (BEC)', responsible_role: 'Director / Procurement Branch' },
  { task_name: 'Prepare bidding / quotation document', responsible_role: 'Procurement Officer' },
  { task_name: 'Send procurement document to relevant committee for review', responsible_role: 'Procurement Officer / TEC / BEC' },
  { task_name: 'Confirm readiness to call bids / quotations', responsible_role: 'Procurement Committee' },
  { task_name: 'Publish / call bids or quotations', responsible_role: 'Procurement Officer' },
  { task_name: 'Conduct pre-bid meeting, if required', responsible_role: 'Procurement Officer / Committee' },
  { task_name: 'Bid / quotation closing', responsible_role: 'Bid Opening Committee / Procurement Officer' },
  { task_name: 'Bid / quotation opening', responsible_role: 'Bid Opening Committee / Procurement Officer' },
  { task_name: 'Send bid / quotation documents to Evaluation Committee', responsible_role: 'Procurement Officer' },
  { task_name: 'Evaluate offers and prepare evaluation report', responsible_role: 'BEC / TEC' },
  { task_name: 'Submit evaluation report and award recommendation', responsible_role: 'BEC / TEC' },
  { task_name: 'Review recommendation and finalize award', responsible_role: 'Procurement Committee' },
  { task_name: 'Issue purchase order / letter of award', responsible_role: 'Procurement Officer' },
  { task_name: 'Receive acceptance / performance security, if applicable', responsible_role: 'Supplier / Procurement Officer' },
  { task_name: 'Complete contract agreement / signing, if applicable', responsible_role: 'Procurement Officer / Supplier' },
  { task_name: 'Update procurement status and file records in PMTS', responsible_role: 'Procurement Officer' },
];

const STATUS_OPTIONS = [
  { value: 'pending', label: 'Pending' },
  { value: 'in_progress', label: 'In Progress' },
  { value: 'completed', label: 'Completed' },
  { value: 'delayed', label: 'Delayed' },
  { value: 'skipped', label: 'Skipped / Not Necessary' },
];

function emptyTask(task = {}) {
  return {
    id: null,
    task_name: task.task_name || '',
    responsible_role: task.responsible_role || '',
    planned_date: '',
    actual_date: '',
    status: 'pending',
    remarks: '',
  };
}

function formatDateForInput(value) {
  if (!value) return '';
  return String(value).slice(0, 10);
}

function normalizeRemarkForUnskip(remarks = '') {
  return String(remarks).replace(/^Skipped:\s*/i, '').trim();
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
  const [tasks, setTasks] = useState(DEFAULT_SCHEDULE_TASKS.map((task) => emptyTask(task)));
  const [stats, setStats] = useState({ total: 0, completed: 0, delayed: 0, skipped: 0, applicable: 0, progress: 0 });
  const [newTask, setNewTask] = useState('');
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
      // Category loading should not block the schedule page.
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
      setStats(result.stats || { total: 0, completed: 0, delayed: 0, skipped: 0, applicable: 0, progress: 0 });

      const scheduleData = Array.isArray(result.data) ? result.data : [];
      if (scheduleData.length > 0) {
        setTasks(
          scheduleData.map((item) => ({
            id: item.id,
            task_name: item.task_name || '',
            responsible_role: item.responsible_role || '',
            planned_date: formatDateForInput(item.planned_date),
            actual_date: formatDateForInput(item.actual_date),
            status: item.status || 'pending',
            remarks: item.remarks || '',
            delay_info: item.delay_info || null,
          }))
        );
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
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [procurementId]);

  const handleTaskChange = (index, field, value) => {
    const updated = [...tasks];
    updated[index] = { ...updated[index], [field]: value };
    setTasks(updated);
  };

  const saveTask = async (task) => {
    if (!task.id) return;
    await api.post('/schedule/update_schedule.php', {
      task_id: task.id,
      planned_date: task.status === 'skipped' ? '' : task.planned_date,
      actual_date: task.status === 'skipped' ? '' : task.actual_date,
      status: task.status,
      remarks: task.remarks,
    });
  };

  const saveAllTasks = async () => {
    setSaving(true);
    setError('');
    setMessage('');

    try {
      const savedTasks = tasks.filter((task) => task.id);
      await Promise.all(savedTasks.map((task) => saveTask(task)));
      setMessage('Procurement time schedule updated successfully. Skipped tasks are ignored from progress and overdue counts.');
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
      planned_date: '',
      actual_date: '',
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
        setMessage('Task skipped successfully.');
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
        setMessage('Task restored to pending.');
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
      'This will replace the current schedule with the standard schedule used for all procurement types. Continue?'
    );
    if (!confirmReset) return;

    setSaving(true);
    setError('');
    setMessage('');

    try {
      await api.post('/schedule/reset_default_ncb_schedule.php', {
        procurement_id: procurementId,
      });
      setMessage('Schedule reset with standard tasks. You can skip tasks that are not necessary.');
      await loadSchedule();
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to reset schedule tasks.');
    } finally {
      setSaving(false);
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
    const headers = ['No', 'Task', 'Responsible Role / Committee', 'Planned Date', 'Actual Date', 'Status', 'Remarks'];
    const rows = tasks.map((task, index) => [
      index + 1,
      task.task_name,
      task.responsible_role,
      task.planned_date,
      task.actual_date,
      task.status,
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
        <div className="print-hide" style={{ marginBottom: '16px' }}>
          <button className="btn btn-outline" type="button" onClick={() => navigate(isITAdmin ? '/admin-settings' : '/officer-management')}>
            <Icon name="back" size={16} /> Back
          </button>
        </div>

        <div className="page-title">Procurement Time Schedule</div>
        <div className="page-subtitle">
          Same schedule is used for all procurement types. Skip tasks that are not necessary for the selected type.
        </div>

        {error && <div className="alert alert-danger print-hide">{error}</div>}
        {message && <div className="alert alert-success print-hide">{message}</div>}

        <div className="form-card" style={{ maxWidth: '1320px' }}>
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

          <div className="form-group">
            <label className="form-label">Title</label>
            <input className="form-input" value={procurement?.title || ''} readOnly />
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
              <label className="form-label">Yellow Delay Alerts</label>
              <input className="form-input" value={stats.yellow_alerts || 0} readOnly />
            </div>
            <div className="form-group">
              <label className="form-label">Red Delay Alerts</label>
              <input className="form-input" value={stats.red_alerts || 0} readOnly />
            </div>
            <div className="form-group">
              <label className="form-label">Payment Date</label>
              <input className="form-input" value={paymentDate || '—'} readOnly />
            </div>
          </div>

          <div className="form-section-title">Schedule Table</div>
          <p className="form-help print-hide">
            Use <strong>Skip</strong> when a task is not needed for Shopping, Direct Purchase, Emergency Procurement, or any special case.
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
                    <th>Actual Date</th>
                    <th>Status</th>
                    <th>Delay Alert</th>
                    <th>Remarks / Skip Reason</th>
                    <th className="print-hide">Action</th>
                  </tr>
                </thead>
                <tbody>
                  {tasks.map((task, index) => {
                    const isSkipped = task.status === 'skipped';
                    const delayInfo = task.delay_info;
                    return (
                      <tr key={task.id || index} style={isSkipped ? { opacity: 0.72 } : undefined}>
                        <td>{index + 1}</td>
                        <td className="task-name">
                          <input
                            type="text"
                            value={task.task_name}
                            onChange={(e) => handleTaskChange(index, 'task_name', e.target.value)}
                            readOnly={Boolean(task.id)}
                          />
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
                                {delayInfo.alert_color === 'red' ? ' Red' : ' Yellow'} - {delayInfo.days_late} day(s)
                              </span>
                              <div className="text-muted text-xs" style={{ marginTop: '4px' }}>{delayInfo.description}</div>
                            </div>
                          ) : (
                            <span className="badge badge-gray">No Delay</span>
                          )}
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
                          {isSkipped ? (
                            <button className="action-btn view" type="button" onClick={() => unskipTask(index)} disabled={saving}>
                              Use Task
                            </button>
                          ) : (
                            <button className="action-btn reject" type="button" onClick={() => skipTask(index)} disabled={saving}>
                              Skip
                            </button>
                          )}
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
      </div>
    </Layout>
  );
}