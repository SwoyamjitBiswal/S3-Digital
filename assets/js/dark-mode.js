// Dark Mode Toggle - Shared Script
document.addEventListener('DOMContentLoaded', function() {
    const darkModeToggle = document.getElementById('darkModeToggle');
    const darkModeIcon = document.getElementById('darkModeIcon');
    const html = document.documentElement;
    
    // Only proceed if toggle exists on the page
    if (!darkModeToggle || !darkModeIcon) {
        return;
    }
    
    // Check for saved theme preference or default to light
    const currentTheme = localStorage.getItem('theme') || 'light';
    
    // Apply the saved theme on page load
    if (currentTheme === 'dark') {
        html.setAttribute('data-theme', 'dark');
        darkModeIcon.classList.remove('fa-moon');
        darkModeIcon.classList.add('fa-sun');
    }
    
    // Toggle theme when button is clicked
    darkModeToggle.addEventListener('click', () => {
        const theme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        
        if (theme === 'dark') {
            html.setAttribute('data-theme', 'dark');
            darkModeIcon.classList.remove('fa-moon');
            darkModeIcon.classList.add('fa-sun');
            localStorage.setItem('theme', 'dark');
        } else {
            html.removeAttribute('data-theme');
            darkModeIcon.classList.remove('fa-sun');
            darkModeIcon.classList.add('fa-moon');
            localStorage.setItem('theme', 'light');
        }
    });
});
