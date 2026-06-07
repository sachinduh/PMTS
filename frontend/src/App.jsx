import { Routes, Route, Navigate } from "react-router-dom";

// Styles
import "./styles/global.css";

// Auth
import Login from "./pages/auth/Login";
import Register from "./pages/auth/Register";

export default function App() {
  return (
    <Routes>
      {/* Public Routes */}
      <Route path="/login" element={<Login />} />
      <Route path="/register" element={<Register />} />
    </Routes>
  );
}
