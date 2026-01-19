<?php
require_once __DIR__ . '/app/layout.php';
$user = require_login();
render_header('Calendar', $user);
?>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>

<div class="card">
    <h1>Financial Calendar</h1>
    <div class="muted">Solid = Past History. Faded = Future Recurring (projected).</div>
</div>

<div class="card" style="padding:0; overflow:hidden;">
    <div id='calendar' style="padding:10px; min-height:600px;"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    
    // Mobile Detection
    var isMobile = window.innerWidth < 700;
    
    var calendar = new FullCalendar.Calendar(calendarEl, {
        // Switch view based on screen size
        initialView: isMobile ? 'listMonth' : 'dayGridMonth',
        
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            // Simplify toolbar on mobile
            right: isMobile ? 'listMonth' : 'dayGridMonth,listWeek'
        },
        
        events: '/api/events.php',
        height: 'auto',
        contentHeight: 600,
        
        // Mobile visual tweaks
        views: {
            listMonth: { buttonText: 'List' },
            dayGridMonth: { buttonText: 'Grid' }
        },
        
        eventClick: function(info) {
            // Show alert with full details on click (useful for mobile touch)
            alert(info.event.title + '\nDate: ' + info.event.start.toLocaleDateString());
        }
    });
    calendar.render();
});
</script>

<?php render_footer(); ?>