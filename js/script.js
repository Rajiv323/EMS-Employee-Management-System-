function login() {
  const username = document.getElementById("username").value;
  const password = document.getElementById("password").value;
  const error = document.getElementById("error");

  fetch("php/login.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ username, password }),
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.status === "error") {
        error.textContent = data.message;
        return;
      }

      const role = data.user.role;       
      const name = data.user.username;

      localStorage.setItem("currentUser", JSON.stringify(data.user));

      if (role === "manager") {
        window.location.href = "manager.html";
      }
      else if (role === "employee") {
        window.location.href = "employee.html";
      }
      else if (role === "hr") {
        window.location.href = "hr.html";
      }
      else {
        error.textContent = "Unknown role!";
      }
    })
    .catch(() => {
      error.textContent = "Server not responding";
    });
}
