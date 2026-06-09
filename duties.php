<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// =========================================================================
// 1. DATABASE AUTOMATIC SETUP (Kila kitu kinatengenezwa hapa kiotomatiki)
// =========================================================================
$host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'duty_management';

try {
    // Unganisha na MySQL kwanza (bila kuchagua database)
    $conn = new PDO("mysql:host=$host;charset=utf8mb4", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Tengeneza Database kama haipo
    $conn->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    
    // Sasa unganisha kwenye database yenyewe rasmi
    $db = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // TENGENEZA MEZA YA USERS KAMA HAIPO
    $db->exec("CREATE TABLE IF NOT EXISTS `users` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `name` VARCHAR(100) NOT NULL,
      `email` VARCHAR(100) UNIQUE NOT NULL,
      `password` VARCHAR(255) NOT NULL,
      `role` VARCHAR(20) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // TENGENEZA MEZA YA DUTY TYPES KAMA HAIPO
    $db->exec("CREATE TABLE IF NOT EXISTS `duty_types` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `duty_name` VARCHAR(100) UNIQUE NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // TENGENEZA MEZA YA DUTIES KAMA HAIPO
    $db->exec("CREATE TABLE IF NOT EXISTS `duties` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `teacher_id` INT NOT NULL,
      `duty_type_id` INT NOT NULL,
      `duty_date` DATE NOT NULL,
      `status` VARCHAR(20) DEFAULT 'Pending',
      FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
      FOREIGN KEY (`duty_type_id`) REFERENCES `duty_types`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

} catch (PDOException $e) {
    die("Database Configuration Failed: " . $e->getMessage());
}

// =========================================================================
// 2. SYSTEMS LOGIC (Login, Register, Admin Actions)
// =========================================================================
$msg = "";
$msg_class = "";

// REGISTER ACCOUNT
if (isset($_POST['register'])) {
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $check_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $role = ($check_users == 0) ? 'Admin' : 'Teacher';

    try {
        $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $password, $role]);
        $msg = "Akaunti imetengenezwa! Ingia sasa kama " . $role;
        $msg_class = "alert-success";
    } catch (PDOException $e) {
        $msg = "Email hii tayari imetumika au kuna makosa.";
        $msg_class = "alert-danger";
    }
}

// LOGIN SYSTEM
if (isset($_POST['login'])) {
    $email = htmlspecialchars($_POST['email']);
    $password = $_POST['password'];

    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        header("Location: index.php");
        exit();
    } else {
        $msg = "Email au Password siyo sahihi!";
        $msg_class = "alert-danger";
    }
}

// ADMIN ACTIONS
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'Admin') {
    
    // Add Duty Type
    if (isset($_POST['add_duty_type'])) {
        $duty_name = htmlspecialchars($_POST['duty_name']);
        try {
            $stmt = $db->prepare("INSERT INTO duty_types (duty_name) VALUES (?)");
            $stmt->execute([$duty_name]);
            $msg = "Aina ya zamu imeongezwa!";
            $msg_class = "alert-success";
        } catch (PDOException $e) {
            $msg = "Aina hii ya zamu tayari ipo.";
            $msg_class = "alert-danger";
        }
    }

    // Add Teacher manually
    if (isset($_POST['add_teacher'])) {
        $name = htmlspecialchars($_POST['t_name']);
        $email = htmlspecialchars($_POST['t_email']);
        $password = password_hash($_POST['t_password'], PASSWORD_DEFAULT);
        
        try {
            $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'Teacher')");
            $stmt->execute([$name, $email, $password]);
            $msg = "Mwalimu ameongezwa kikamilifu!";
            $msg_class = "alert-success";
        } catch (PDOException $e) {
            $msg = "Email imeshajisajili.";
            $msg_class = "alert-danger";
        }
    }

    // Assign Duty
    if (isset($_POST['assign_duty'])) {
        $t_id = $_POST['teacher_id'];
        $d_id = $_POST['duty_type_id'];
        $d_date = $_POST['duty_date'];

        $stmt = $db->prepare("INSERT INTO duties (teacher_id, duty_type_id, duty_date) VALUES (?, ?, ?)");
        $stmt->execute([$t_id, $d_id, $d_date]);
        $msg = "Zamu imepangwa kikamilifu!";
        $msg_class = "alert-success";
    }
}

// LOGOUT
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Duty Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .navbar { background-color: #4e73df; }
        .card { border: none; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn-primary { background-color: #4e73df; border: none; }
        .btn-primary:hover { background-color: #2e59d9; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">🏫 MFUMO WA USIMAMIZI WA MAJUKUMU KATIKA SHULE YA SEKONDARI</a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <span class="navbar-text text-white me-3">Habari, <?php echo $_SESSION['user_name']; ?> (<?php echo $_SESSION['user_role']; ?>)</span>
            <a href="index.php?logout=1" class="btn btn-danger btn-sm">Logout</a>
        <?php endif; ?>
    </div>
</nav>

<div class="container">
    
    <?php if ($msg != ""): ?>
        <div class="alert <?php echo $msg_class; ?> alert-dismissible fade show" role="alert">
            <?php echo $msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!isset($_SESSION['user_id'])): ?>
        <div class="row justify-content-center">
            <div class="col-md-5 mb-4">
                <div class="card p-4">
                    <h3 class="text-center mb-4 fw-bold text-primary">Ingia Kwenye Mfumo</h3>
                    <form action="index.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required placeholder="mfano@shule.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required placeholder="******">
                        </div>
                        <button type="submit" name="login" class="btn btn-primary w-100 py-2">Ingia</button>
                    </form>
                </div>
            </div>

            <div class="col-md-5 mb-4">
                <div class="card p-4">
                    <h3 class="text-center mb-4 fw-bold text-success">Tengeneza Akaunti</h3>
                    <form action="index.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Majina Kamili</label>
                            <input type="text" name="name" class="form-control" required placeholder="Mwl. Juma Hamis">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required placeholder="juma@shule.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required placeholder="******">
                        </div>
                        <button type="submit" name="register" class="btn btn-success w-100 py-2">Sajili Akaunti</button>
                    </form>
                    
                </div>
            </div>
        </div>

    <?php else: ?>
        <?php if ($_SESSION['user_role'] == 'Admin'): ?>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card p-3">
                        <h5 class="fw-bold text-primary mb-3">Sajili Mwalimu Mpya</h5>
                        <form action="index.php" method="POST">
                            <div class="mb-2"><input type="text" name="t_name" class="form-control" placeholder="Jina la Mwalimu" required></div>
                            <div class="mb-2"><input type="email" name="t_email" class="form-control" placeholder="Email ya Mwalimu" required></div>
                            <div class="mb-2"><input type="password" name="t_password" class="form-control" placeholder="Password yake" required></div>
                            <button type="submit" name="add_teacher" class="btn btn-primary btn-sm w-100">Ongeza Mwalimu</button>
                        </form>
                    </div>
                </div>

                <div class="col-md-4 mb-4">
                    <div class="card p-3">
                        <h5 class="fw-bold text-primary mb-3">Ongeza Aina ya Zamu</h5>
                        <form action="index.php" method="POST">
                            <div class="mb-2"><input type="text" name="duty_name" class="form-control" placeholder="Mfano: Zamu ya Usafi" required></div>
                            <button type="submit" name="add_duty_type" class="btn btn-primary btn-sm w-100">Ongeza Zamu</button>
                        </form>
                    </div>
                </div>

                <div class="col-md-4 mb-4">
                    <div class="card p-3">
                        <h5 class="fw-bold text-primary mb-3">Panga Zamu kwa Mwalimu</h5>
                        <form action="index.php" method="POST">
                            <div class="mb-2">
                                <select name="teacher_id" class="form-select" required>
                                    <option value="">Chagua Mwalimu...</option>
                                    <?php 
                                    $teachers = $db->query("SELECT * FROM users WHERE role = 'Teacher'")->fetchAll();
                                    foreach($teachers as $t) echo "<option value='{$t['id']}'>{$t['name']}</option>";
                                    ?>
                                </select>
                            </div>
                            <div class="mb-2">
                                <select name="duty_type_id" class="form-select" required>
                                    <option value="">Chagua Aina ya Zamu...</option>
                                    <?php 
                                    $duties_t = $db->query("SELECT * FROM duty_types")->fetchAll();
                                    foreach($duties_t as $dt) echo "<option value='{$dt['id']}'>{$dt['duty_name']}</option>";
                                    ?>
                                </select>
                            </div>
                            <div class="mb-2"><input type="date" name="duty_date" class="form-control" required></div>
                            <button type="submit" name="assign_duty" class="btn btn-success btn-sm w-100">Panga Zamu Hii</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card p-4 mt-2">
                <h4 class="fw-bold text-dark mb-3">Ratiba ya Zamu Shuleni (Zote)</h4>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-primary">
                            <tr>
                                <th>Mwalimu</th>
                                <th>Aina ya Zamu</th>
                                <th>Tarehe ya Zamu</th>
                                <th>Hali (Status)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $q = "SELECT u.name, dt.duty_name, d.duty_date, d.status 
                                  FROM duties d 
                                  JOIN users u ON d.teacher_id = u.id 
                                  JOIN duty_types dt ON d.duty_type_id = dt.id 
                                  ORDER BY d.duty_date DESC";
                            $all_duties = $db->query($q)->fetchAll();
                            if(count($all_duties) > 0){
                                foreach($all_duties as $row){
                                    echo "<tr>
                                            <td>{$row['name']}</td>
                                            <td>{$row['duty_name']}</td>
                                            <td>{$row['duty_date']}</td>
                                            <td><span class='badge bg-warning text-dark'>{$row['status']}</span></td>
                                          </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='4' class='text-center'>Hakuna zamu yoyote iliyopangwa bado.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <div class="card p-4">
                <h4 class="fw-bold text-dark mb-3">Zamu Zako Ulizopangiwa</h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-success">
                            <tr>
                                <th>Aina ya Zamu</th>
                                <th>Tarehe ya Zamu</th>
                                <th>Hali (Status)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $my_id = $_SESSION['user_id'];
                            $stmt = $db->prepare("SELECT dt.duty_name, d.duty_date, d.status 
                                                  FROM duties d 
                                                  JOIN duty_types dt ON d.duty_type_id = dt.id 
                                                  WHERE d.teacher_id = ? 
                                                  ORDER BY d.duty_date ASC");
                            $stmt->execute([$my_id]);
                            $my_duties = $stmt->fetchAll();

                            if(count($my_duties) > 0){
                                foreach($my_duties as $row){
                                    echo "<tr>
                                            <td class='fw-bold'>{$row['duty_name']}</td>
                                            <td>{$row['duty_date']}</td>
                                            <td><span class='badge bg-info text-dark'>{$row['status']}</span></td>
                                          </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='3' class='text-center text-muted'>Hongera! Hujapangiwa zamu yoyote kwa sasa.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
