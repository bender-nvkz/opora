import React from "react";
import ReactDOM from "react-dom/client";

const rootElement = document.getElementById("root");
if (!rootElement) throw new Error("Root element not found");

ReactDOM.createRoot(rootElement).render(
    <React.StrictMode>
        <div>
            <h1>Опора PIM</h1>
            <p>Skeleton — Этап 0</p>
        </div>
    </React.StrictMode>,
);
