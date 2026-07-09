import { useState, useEffect } from 'react';
import Layout from '../../components/Layout';
import api, { getSystemSettings, updateSystemSettings } from '../../api/api';

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
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState('');

  const loadCategories = async () => {
    const res = await api.get('/schedule/get_ncb_categories.php');
    setCategories(res.data?.categories || []);
  };

  useEffect(() => {
    Promise.all([getSystemSettings(), loadCategories()])
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
