            </main>
        </div>
    </div>
    
    <script>
    // General JS helpers for form toggling and confirmation
    function toggleForm(formId) {
        const form = document.getElementById(formId);
        if (form) {
            form.classList.toggle('show');
            if (form.classList.contains('show')) {
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    }

    function confirmDelete(message = "Are you sure you want to delete this record?") {
        return confirm(message);
    }
    </script>
</body>
</html>
