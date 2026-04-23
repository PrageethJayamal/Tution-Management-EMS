<?php
require_once '../config/db.php';
require_once '../includes/header.php';

if ($_SESSION['role'] !== 'admin') die("<div class='alert alert-error'>Access Denied</div>");

$c_id = $_SESSION['center_id'];
$stmt = $pdo->prepare("SELECT c.*, f.first_name, f.last_name FROM classes c LEFT JOIN faculty f ON c.faculty_id = f.id WHERE c.center_id = ? AND c.day_of_week IS NOT NULL");
$stmt->execute([$c_id]);
$classes = $stmt->fetchAll();

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
// Setting the calendar grid bounds
$start_hour = 8; // 8:00 AM
$end_hour = 19;  // 7:00 PM (Drawing grid up to 7 PM)
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>Visual Timetable Planner</h2>
    <a href="classes.php" class="btn btn-primary" style="text-decoration: none;">+ Manage Classes</a>
</div>

<div class="card">
    <div class="card-header">Center Master Schedule</div>
    <div style="overflow-x: auto; padding: 20px 0;">
        <div style="min-width: 900px; display: flex; border: 1px solid var(--border); border-radius: 8px; background: white; position: relative;">
            
            <!-- Time Column -->
            <div style="flex: 0 0 80px; border-right: 1px solid var(--border); background: #f9fafb; z-index: 10;">
                <div style="height: 50px; border-bottom: 1px solid var(--border);"></div> <!-- Header spacer -->
                <?php for($h = $start_hour; $h <= $end_hour; $h++): ?>
                    <div style="height: 60px; border-bottom: 1px solid var(--border); position: relative; padding: 5px; text-align: right; font-size: 11px; color: var(--text-muted); box-sizing: border-box;">
                        <?php echo date('g A', strtotime("$h:00")); ?>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- Day Columns -->
            <?php foreach($days as $day): ?>
                <div style="flex: 1; border-right: 1px solid var(--border); position: relative; min-width: 120px;">
                    <!-- Day Header -->
                    <div style="height: 50px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: center; font-weight: bold; background: #f9fafb; color: var(--text-main); position: sticky; top: 0; z-index: 5;">
                        <?php echo $day; ?>
                    </div>
                    
                    <!-- Grid Lines background rendering -->
                    <div style="position: absolute; top: 50px; left: 0; right: 0; bottom: 0; background-image: linear-gradient(to bottom, var(--border) 1px, transparent 1px); background-size: 100% 60px; z-index: 1;"></div>

                    <!-- Rendering Classes for this specific day -->
                    <?php 
                    foreach($classes as $c) {
                        if ($c['day_of_week'] === $day) {
                            $st = strtotime($c['start_time']);
                            $et = strtotime($c['end_time']);
                            
                            $s_hour = (int)date('H', $st);
                            $s_min = (int)date('i', $st);
                            
                            $e_hour = (int)date('H', $et);
                            $e_min = (int)date('i', $et);
                            
                            // Calculate Top absolute positioning (50px header + (hours past start_hour * 60) + minutes)
                            $top_offset = 50 + (($s_hour - $start_hour) * 60) + $s_min;
                            
                            // Calculate Height in relative minutes
                            $duration_mins = (($e_hour - $s_hour) * 60) + ($e_min - $s_min);
                            
                            // Only draw if it's within our logical grid scope
                            if ($s_hour >= $start_hour && $s_hour <= $end_hour) {
                                ?>
                                <div style="position: absolute; top: <?php echo $top_offset; ?>px; left: 4px; right: 4px; height: <?php echo max($duration_mins, 20); ?>px; background: rgba(79, 70, 229, 0.08); border-left: 4px solid var(--primary); border-radius: 4px; padding: 6px; font-size: 11px; overflow: hidden; box-sizing: border-box; box-shadow: 0 1px 3px rgba(0,0,0,0.1); z-index: 2; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.boxShadow='0 4px 6px rgba(0,0,0,0.1)'; this.style.transform='scale(1.02)';" onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)'; this.style.transform='scale(1)';">
                                    <div style="font-weight: 700; color: var(--primary); margin-bottom: 3px; white-space: nowrap; text-overflow: ellipsis; overflow: hidden; font-size: 12px;"><?php echo htmlspecialchars($c['name']); ?></div>
                                    <div style="color: var(--text-main); font-weight: 500; font-size: 10px; margin-bottom: 2px;">⏰ <?php echo date('g:i A', $st) . ' - ' . date('g:i A', $et); ?></div>
                                    <div style="color: var(--text-muted); font-style: italic; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 10px;">
                                        👩‍🏫 <?php echo $c['first_name'] ? htmlspecialchars($c['first_name'].' '.$c['last_name']) : 'Unassigned'; ?>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                    }
                    ?>
                </div>
            <?php endforeach; ?>
            
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
