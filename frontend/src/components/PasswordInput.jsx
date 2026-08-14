import { useState } from "react";

export default function PasswordInput({ className = "form-input", ...inputProps }) {
  const [visible, setVisible] = useState(false);

  return (
    <div className="password-input-wrap">
      <input
        {...inputProps}
        className={`${className} password-input-control`.trim()}
        type={visible ? "text" : "password"}
      />
      <button
        type="button"
        className="password-visibility-btn"
        onClick={() => setVisible((current) => !current)}
        aria-label={visible ? "Hide password" : "Show password"}
        title={visible ? "Hide password" : "Show password"}
      >
        {visible ? (
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="m3.27 2 18.73 18.73-1.27 1.27-3.2-3.2A11.8 11.8 0 0 1 12 20C6.5 20 2.27 15.89 1 12c.58-1.78 1.78-3.65 3.54-5.14L2 4.27 3.27 2Zm3.32 6.89A5.95 5.95 0 0 0 4.18 12C5.47 15.03 8.5 18 12 18c1.46 0 2.85-.52 4.05-1.3l-1.58-1.58A4 4 0 0 1 8.88 9.53L6.59 7.24v1.65Zm4.17.07 4.28 4.28A4 4 0 0 0 10.76 8.96ZM12 4c5.5 0 9.73 4.11 11 8a12.5 12.5 0 0 1-2.72 4.46l-1.42-1.42A10.17 10.17 0 0 0 20.82 12C19.53 8.97 16.5 6 12 6c-.71 0-1.4.12-2.06.33L8.3 4.69A11.4 11.4 0 0 1 12 4Z" />
          </svg>
        ) : (
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 4c5.5 0 9.73 4.11 11 8-1.27 3.89-5.5 8-11 8S2.27 15.89 1 12c1.27-3.89 5.5-8 11-8Zm0 2c-4.05 0-7.17 2.83-8.82 6C4.83 15.17 7.95 18 12 18s7.17-2.83 8.82-6C19.17 8.83 16.05 6 12 6Zm0 2a4 4 0 1 1 0 8 4 4 0 0 1 0-8Zm0 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z" />
          </svg>
        )}
      </button>
    </div>
  );
}
