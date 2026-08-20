import Sidebar from './Sidebar';
import Topbar from './Topbar';
import BackToDashboard from './BackToDashboard';

export default function Layout({ children }) {
  return (
    <div className="app-layout">
      <Sidebar />
      <div className="app-main">
        <Topbar />
        <main className="app-content">
          <BackToDashboard />
          {children}
        </main>
      </div>
    </div>
  );
}
