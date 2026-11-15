
document.addEventListener("DOMContentLoaded", function () {
    const passwordInput = document.getElementById("loginPassword");
    const toggleButton = document.getElementById("togglePassword");
    const toggleIcon = toggleButton ? toggleButton.querySelector("img") : null;

    if (!passwordInput || !toggleButton || !toggleIcon) {
        return;
    }

    toggleButton.addEventListener("click", function () {
        const isPasswordHidden = passwordInput.type === "password";

        passwordInput.type = isPasswordHidden ? "text" : "password";

        toggleIcon.src = isPasswordHidden
            ? toggleButton.dataset.hideIcon
            : toggleButton.dataset.showIcon;

        toggleButton.setAttribute(
            "aria-label",
            isPasswordHidden ? "Hide password" : "Show password"
        );

        toggleButton.setAttribute(
            "aria-pressed",
            isPasswordHidden ? "true" : "false"
        );

        passwordInput.focus();
    });
});
