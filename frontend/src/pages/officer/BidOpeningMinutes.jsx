import { useEffect, useState } from 'react';
import Layout from '../../components/Layout';

const API = 'http://localhost/PMTS/backend/suppliers';

export default function SupplierManagement() {
  const empty = { supplier_name:'', contact_person:'', email:'', phone:'', address:'' };
  const [form, setForm] = useState(empty);
  const [suppliers, setSuppliers] = useState([]);
  const [message, setMessage] = useState('');
  const load = async () => { const r = await fetch(`${API}/get_suppliers.php`); const d = await r.json(); setSuppliers(d.suppliers || []); };
  useEffect(()=>{ load(); }, []);
  const save = async e => { e.preventDefault(); const r = await fetch(`${API}/create_supplier.php`, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(form)}); const d = await r.json(); setMessage(d.message); if(d.success){ setForm(empty); load(); }};
  const remove = async id => { if(!window.confirm('Delete supplier?')) return; const r = await fetch(`${API}/delete_supplier.php`, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id})}); const d = await r.json(); setMessage(d.message); load(); };
  return <Layout><div className="page-wrapper animate-fade-in"><div className="page-title"> Supplier Management</div><div className="page-subtitle">Create and manage supplier/vendor records</div>{message && <div className="alert alert-success">{message}</div>}<div className="card" style={{padding:20}}><form onSubmit={save} className="form-grid"><label>Supplier Name<input value={form.supplier_name} onChange={e=>setForm({...form,supplier_name:e.target.value})} required /></label><label>Contact Person<input value={form.contact_person} onChange={e=>setForm({...form,contact_person:e.target.value})} /></label><label>Email<input type="email" value={form.email} onChange={e=>setForm({...form,email:e.target.value})} /></label><label>Phone<input value={form.phone} onChange={e=>setForm({...form,phone:e.target.value})} /></label><label>Address<textarea value={form.address} onChange={e=>setForm({...form,address:e.target.value})} /></label><button className="btn btn-primary" type="submit">Add Supplier</button></form></div><div className="card" style={{padding:20, marginTop:20}}><table className="data-table"><thead><tr><th>Name</th><th>Contact</th><th>Email</th><th>Phone</th><th>Address</th><th>Action</th></tr></thead><tbody>{suppliers.map(s=><tr key={s.id}><td>{s.supplier_name}</td><td>{s.contact_person}</td><td>{s.email}</td><td>{s.phone}</td><td>{s.address}</td><td><button className="action-btn reject" onClick={()=>remove(s.id)}>Delete</button></td></tr>)}</tbody></table></div></div></Layout>;
}