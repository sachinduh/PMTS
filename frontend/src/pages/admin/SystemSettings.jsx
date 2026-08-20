import { useState, useEffect } from 'react';
import Layout from '../../components/Layout';
import api, { getSystemSettings, updateSystemSettings, getTaskDelaySettings, saveTaskDelaySetting } from '../../api/api';

export default function SystemSettings() {
  const [settings, setSettings] = useState({
    system_name: 'PMTS - Procurement Management & Tracking System',
    hospital_name: '',
    contact_email: '',
    max_procurement_amount: '',
    auto_backup: '1',
  });
  const [categories, setCategories] = useState([]);
  const [newCategory, setNewCategory] = useState('');
  const [categoryMessage, setCategoryMessage] = useState('');
  const [taskDelaySettings, setTaskDelaySettings] = useState([]);
  const [taskDelayForm, setTaskDelayForm] = useState({ task_name: '', allowed_delay_days: '' });
  const [taskDelayMessage, setTaskDelayMessage] = useState('');
  const [taskDelaySaving, setTaskDelaySaving] = useState(false);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState('');

  const loadCategories = async () => {
    const res = await api.get('/schedule/get_ncb_categories.php');
    setCategories(res.data?.categories || []);
  };

  const loadTaskDelaySettings = async () => {
    const res = await getTaskDelaySettings();
    const tasks = res.data?.tasks || [];
    setTaskDelaySettings(tasks);
    if (!taskDelayForm.task_name && tasks.length > 0) {
      setTaskDelayForm({
        task_name: tasks[0].task_name,
        allowed_delay_days: String(tasks[0].allowed_delay_days ?? 0),
      });
    }
  };

  useEffect(() => {
    Promise.all([getSystemSettings(), loadCategories(), loadTaskDelaySettings()])
      .then(([res]) => setSettings(prev => ({ ...prev, ...(res.data?.settings || {}) })))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  const handleChange = (e) => setSettings({ ...settings, [e.target.name]: e.target.value });

  const addNcbCategory = async () => {
    const categoryName = newCategory.trim();
    if (!categoryName) {
      setCategoryMessage('Please enter a category name.');
      return;
    }

    try {
      await api.post('/schedule/create_ncb_category.php', { category_name: categoryName });
      setNewCategory('');
      setCategoryMessage('Category added successfully.');
      await loadCategories();
    } catch (err) {
      setCategoryMessage(err.response?.data?.message || 'Failed to add category.');
    }
  };

  const handleTaskDelaySelect = (taskName) => {
    const task = taskDelaySettings.find((item) => item.task_name === taskName);
    setTaskDelayForm({
      task_name: taskName,
      allowed_delay_days: task ? String(task.allowed_delay_days ?? 0) : '',
    });
    setTaskDelayMessage('');
  };

  const saveSelectedTaskDelay = async () => {
    if (!taskDelayForm.task_name) {
      setTaskDelayMessage('Please select a schedule task first.');
      return;
    }

    const days = Number.parseInt(taskDelayForm.allowed_delay_days, 10);
    if (!Number.isFinite(days) || days < 0) {
      setTaskDelayMessage('Allowed delay duration must be 0 or more days.');
      return;
    }

    setTaskDelaySaving(true);
    setTaskDelayMessage('');
    try {
      await saveTaskDelaySetting({
        task_name: taskDelayForm.task_name,
        allowed_delay_days: days,
      });
      setTaskDelayMessage('Allowed delay duration saved. New procurements will use this setting.');
      await loadTaskDelaySettings();
    } catch (err) {
      setTaskDelayMessage(err.response?.data?.message || 'Failed to save allowed delay duration.');
    } finally {
      setTaskDelaySaving(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    try {
      await updateSystemSettings(settings);
      setMessage('Settings saved successfully.');
    } catch {
      setMessage('Failed to save settings.');
    }
    setSaving(false);
  };

  if (loading) return <Layout><div className="loading-page"><div className="spinner" /></div></Layout>;

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="page-title"> System Settings</div>
        <div className="page-subtitle">Configure global system preferences</div>

        {message && <div className="alert alert-info">{message}</div>}

        <div className="form-card">
          <form onSubmit={handleSubmit}>
            <div className="form-section-title" style={{ marginTop: 0 }}>General Settings</div>
            <div className="form-group">
              <label className="form-label">System Name</label>
              <input className="form-input" name="system_name" value={settings.system_name} onChange={handleChange} />
            </div>
            <div className="form-group">
              <label className="form-label">Hospital / Organization Name</label>
              <input className="form-input" name="hospital_name" value={settings.hospital_name} onChange={handleChange} />
            </div>
            <div className="form-row">
              <div className="form-group">
                <label className="form-label">Contact Email</label>
                <input className="form-input" name="contact_email" type="email" value={settings.contact_email} onChange={handleChange} />
              </div>
              <div className="form-group">
                <label className="form-label">Max Procurement Amount (Rs.)</label>
                <input className="form-input" name="max_procurement_amount" type="number" value={settings.max_procurement_amount} onChange={handleChange} />
              </div>
            </div>
            <div className="form-group">
              <label className="form-label">Auto Backup</label>
              <select className="form-select" name="auto_backup" value={settings.auto_backup} onChange={handleChange}>
                <option value="1">Enabled</option>
                <option value="0">Disabled</option>
              </select>
            </div>
            <div className="form-section-title">Schedule Categories</div>
            <p className="form-help">IT Admin can add custom categories. These categories appear in Create Procurement and Procurement Time Schedule.</p>
            {categoryMessage && <div className="alert alert-info">{categoryMessage}</div>}
            <div className="form-row">
              <div className="form-group">
                <label className="form-label">New Category</label>
                <input
                  className="form-input"
                  value={newCategory}
                  onChange={(e) => setNewCategory(e.target.value)}
                  placeholder="e.g. Surgical Items"
                />
              </div>
              <div className="form-group">
                <label className="form-label">Action</label>
                <button className="btn btn-outline" type="button" onClick={addNcbCategory}>
                  + Add Category
                </button>
              </div>
            </div>
            <div className="form-group">
              <label className="form-label">Available Categories</label>
              <div className="form-help">{categories.length ? categories.join(', ') : 'No categories found.'}</div>
            </div>

            <div className="form-section-title">Create New Procurement Time Schedule Delay Setup</div>
            <p className="form-help">
              Configure how many days each standard schedule task may delay before PMTS marks it as delayed. First select one task, enter the allowed duration, then save it. These values are used automatically when a new procurement time schedule is created.
            </p>
            {taskDelayMessage && <div className="alert alert-info">{taskDelayMessage}</div>}
            <div className="form-row" style={{ alignItems: 'end' }}>
              <div className="form-group">
                <label className="form-label">Select Schedule Task</label>
                <select
                  className="form-select"
                  value={taskDelayForm.task_name}
                  onChange={(e) => handleTaskDelaySelect(e.target.value)}
                >
                  <option value="">— Select schedule task —</option>
                  {taskDelaySettings.map((task) => (
                    <option key={task.task_name} value={task.task_name}>
                      Task {task.sort_order}: {task.task_name}
                    </option>
                  ))}
                </select>
              </div>
              <div className="form-group">
                <label className="form-label">Allowed Delay Duration (Days)</label>
                <input
                  className="form-input"
                  type="number"
                  min="0"
                  max="3650"
                  placeholder="e.g. 3"
                  value={taskDelayForm.allowed_delay_days}
                  onChange={(e) => setTaskDelayForm(prev => ({ ...prev, allowed_delay_days: e.target.value }))}
                />
              </div>
              <div className="form-group">
                <label className="form-label">Action</label>
                <button className="btn btn-primary" type="button" onClick={saveSelectedTaskDelay} disabled={taskDelaySaving}>
                  {taskDelaySaving ? 'Saving…' : 'Save Delay Days'}
                </button>
              </div>
            </div>
            <div className="schedule-table-wrapper" style={{ marginTop: '10px' }}>
              <table className="schedule-table compact-schedule-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Task</th>
                    <th>Responsible Role</th>
                    <th>Allowed Delay</th>
                  </tr>
                </thead>
                <tbody>
                  {taskDelaySettings.map((task) => (
                    <tr key={`delay-setting-${task.task_name}`}>
                      <td>{task.sort_order}</td>
                      <td>{task.task_name}</td>
                      <td>{task.responsible_role || '—'}</td>
                      <td><span className="badge badge-info">{task.allowed_delay_days || 0} day(s)</span></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="form-footer">
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving…' : ' Save Settings'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Layout>
  );
}
