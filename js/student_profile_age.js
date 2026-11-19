(function () {
    function computeAgeFromDateString(dateString) {
        if (!dateString) {
            return "";
        }

        const parts = dateString.split("-");
        if (parts.length !== 3) {
            return "";
        }

        const year = Number(parts[0]);
        const month = Number(parts[1]);
        const day = Number(parts[2]);

        if (!Number.isInteger(year) || !Number.isInteger(month) || !Number.isInteger(day)) {
            return "";
        }

        const birthDate = new Date(year, month - 1, day);
        if (
            birthDate.getFullYear() !== year ||
            birthDate.getMonth() !== month - 1 ||
            birthDate.getDate() !== day
        ) {
            return "";
        }

        const today = new Date();
        const todayMidnight = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        if (birthDate > todayMidnight) {
            return "";
        }

        let age = today.getFullYear() - year;
        const monthDiff = today.getMonth() - (month - 1);
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < day)) {
            age -= 1;
        }

        return age >= 0 ? String(age) : "";
    }

    function syncAgeField() {
        const birthdateInput = document.getElementById("birthdate");
        const ageInput = document.getElementById("age");
        if (!birthdateInput || !ageInput) {
            return;
        }

        ageInput.value = computeAgeFromDateString(birthdateInput.value);
    }

    document.addEventListener("DOMContentLoaded", function () {
        const birthdateInput = document.getElementById("birthdate");
        const form = birthdateInput ? birthdateInput.closest("form") : null;

        syncAgeField();

        if (birthdateInput) {
            birthdateInput.addEventListener("change", syncAgeField);
            birthdateInput.addEventListener("input", syncAgeField);
        }

        if (form) {
            form.addEventListener("reset", function () {
                setTimeout(syncAgeField, 0);
            });
        }
    });
})();