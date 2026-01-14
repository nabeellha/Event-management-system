<?php
// --- 1. ERROR REPORTING & DB CONNECT (Smart Feature: Developer Mode) ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'db.php';

// --- INITIALIZE VARIABLES ---
$current_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// --- HELPER: Smart Image Selection ---
function getEventImage($category, $subcategory, $theme) {
    $sub = strtolower($subcategory);
    $thm = strtolower($theme);
    $cat = strtolower($category);

    if (strpos($sub, 'qawali') !== false) return 'assets/sub_qawali.jpg';
    if (strpos($sub, 'sufi') !== false) return 'assets/sub_qawali.jpg';
    if (strpos($sub, 'mehnd') !== false) return 'assets/sub_mehnd.jpg';
    if (strpos($sub, 'barat') !== false) return 'assets/sub_barat.jpg';
    if (strpos($sub, 'walima') !== false) return 'assets/sub_walima.jpg';
    if (strpos($sub, 'pop') !== false) return 'assets/sub_pop.jpg';
    if (strpos($sub, 'rock') !== false) return 'assets/sub_pop.jpg';
    
    if (strpos($thm, 'red') !== false) return 'assets/theme_red_gold.jpg';
    if (strpos($thm, 'floral') !== false) return 'assets/theme_floral.jpg';
    if (strpos($thm, 'neon') !== false) return 'assets/theme_neon.jpg';
    if (strpos($thm, 'corporate') !== false) return 'assets/theme_corporate_blue.jpg';

    return 'assets/' . $cat . '.jpg';
}

// --- BACKEND LOGIC ---

// 1. Register
if (isset($_POST['register'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $pass = $_POST['password']; 
    $role = $_POST['role'];
    
    $check = $conn->query("SELECT * FROM users WHERE email='$email'");
    if ($check->num_rows > 0) {
        $error = "Email already registered!";
    } else {
        $sql = "INSERT INTO users (full_name, email, phone, password, role) VALUES ('$name', '$email', '$phone', '$pass', '$role')";
        if ($conn->query($sql) === TRUE) {
            $_SESSION['user_id'] = $conn->insert_id;
            $_SESSION['name'] = $name;
            $_SESSION['role'] = $role;
            $current_role = $role;
            header("Location: index.php");
            exit();
        } else { $error = "Error: " . $conn->error; }
    }
}

// 2. Login
if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $pass = $_POST['password'];
    $result = $conn->query("SELECT * FROM users WHERE email='$email' AND password='$pass'");
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $_SESSION['user_id'] = $row['user_id'];
        $_SESSION['name'] = $row['full_name'];
        $_SESSION['role'] = $row['role'];
        $current_role = $row['role'];
        header("Location: index.php");
        exit();
    } else { $error = "Invalid email or password"; }
}

// 3. Logout
if (isset($_GET['logout'])) { 
    session_destroy(); 
    header("Location: index.php"); 
    exit(); 
}

// 4. Create Event
if (isset($_POST['create_event'])) {
    if ($current_role !== 'host') die("Unauthorized");

    $host_id = $_SESSION['user_id'];
    $name = $_POST['event_name'];
    $audience = $_POST['audience'];
    $cat = $_POST['category'];
    $sub = $_POST['subcategory'];
    $prov = $_POST['province'];
    $city = $_POST['city'];
    $venue = $_POST['venue'];
    $date = $_POST['date'];
    $time = $_POST['time']; // New Time Field
    $guests = $_POST['guests'];
    $theme = $_POST['theme_name'];
    $price = $_POST['total_price_hidden'];
    $foods = isset($_POST['food_items']) ? implode(',', $_POST['food_items']) : '';
    $special_guests = isset($_POST['special_guests']) ? implode(',', $_POST['special_guests']) : '';

    // Updated Query to include 'time'
    $sql = "INSERT INTO events (host_id, name, audience, category, subcategory, province, city, venue, date, time, guests, theme, foods, special_guests, total_price) 
            VALUES ('$host_id', '$name', '$audience', '$cat', '$sub', '$prov', '$city', '$venue', '$date', '$time', '$guests', '$theme', '$foods', '$special_guests', '$price')";
    
    if ($conn->query($sql) === TRUE) { $success = "Event Created Successfully!"; } 
    else { $error = "Error: " . $conn->error; }
}

// 5. Book Ticket (Smart Logic)
if (isset($_POST['book_ticket'])) {
    if ($current_role !== 'attendee') {
        $error = "Only attendees can book tickets.";
    } else {
        $event_id = $_POST['event_id'];
        $uid = $_SESSION['user_id'];
        
        $check = $conn->query("SELECT * FROM bookings WHERE event_id='$event_id' AND user_id='$uid'");
        if ($check->num_rows > 0) {
            $error = "You have already booked this ticket!";
        } else {
            $sql = "INSERT INTO bookings (event_id, user_id) VALUES ('$event_id', '$uid')";
            if ($conn->query($sql) === TRUE) {
                $success = "Booking Confirmed! Check 'My Booked Tickets'.";
            } else {
                $error = "Booking Failed: " . $conn->error;
            }
        }
    }
}

// 6. Delete Event
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $uid = $_SESSION['user_id'];
    $conn->query("DELETE FROM events WHERE event_id=$id AND host_id=$uid");
    header("Location: index.php");
    exit();
}

// --- FETCH DATA ---
$public_events = null;
$my_events = null;
$my_bookings = null;

$check_table = $conn->query("SHOW TABLES LIKE 'events'");
if($check_table && $check_table->num_rows > 0) {
    $public_events = $conn->query("SELECT * FROM events WHERE audience='public' ORDER BY date ASC");
    if ($current_role == 'host' && $user_id) {
        $my_events = $conn->query("SELECT * FROM events WHERE host_id=$user_id ORDER BY date ASC");
    }
    if ($current_role == 'attendee' && $user_id) {
        $my_bookings = $conn->query("
            SELECT events.*, bookings.booking_date 
            FROM events 
            JOIN bookings ON events.event_id = bookings.event_id 
            WHERE bookings.user_id = $user_id 
            ORDER BY events.date ASC
        ");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lumina - Event Management</title>
    
    <!-- 2. BASE TAG (Smart Feature) -->
    <base href="http://localhost/eventhub/"> 
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: { colors: { navy: '#0f172a', lumina: '#1e293b', gold: '#fbbf24', accent: '#6366f1' } }
            }
        }
    </script>
    <style>
        body { background-color: #0f172a; color: white; font-family: 'Segoe UI', sans-serif; }
        
        /* Hero Background with Image */
        .hero-bg {
            background-image: linear-gradient(to bottom, rgba(15, 23, 42, 0.6), rgba(15, 23, 42, 0.95)), url('assets/hero-bg.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            border-radius: 0 0 2rem 2rem;
        }

        .glass { background: rgba(30, 41, 59, 0.8); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); }
        .form-input { width: 100%; background: #334155; border: 1px solid #475569; padding: 12px; border-radius: 8px; color: white; margin-bottom: 15px; }
        .hidden { display: none; }
        .tab-active { border-bottom: 2px solid #6366f1; color: #6366f1; }
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: #1e293b; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #475569; border-radius: 3px; }
    </style>
</head>
<body class="flex flex-col min-h-screen">

    <!-- NAVBAR -->
    <nav class="bg-lumina/90 backdrop-blur border-b border-gray-700 p-4 sticky top-0 z-50 shadow-lg">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center gap-2 cursor-pointer" onclick="showSection('home')">
                <div class="w-8 h-8 bg-gradient-to-tr from-blue-500 to-purple-600 rounded flex items-center justify-center"><i class="fas fa-gem text-white"></i></div>
                <span class="text-2xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-purple-400">LUMINA</span>
            </div>
            <div class="flex items-center gap-4">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <span class="text-gray-300 hidden md:inline">Welcome, <span class="font-bold text-accent"><?php echo $_SESSION['name']; ?></span></span>
                    <span class="text-xs bg-gray-700 px-2 py-1 rounded uppercase"><?php echo $_SESSION['role']; ?></span>
                    <?php if($current_role == 'host'): ?>
                        <button onclick="showSection('create')" class="bg-accent px-4 py-2 rounded text-sm font-bold hover:bg-indigo-600 transition shadow-lg shadow-indigo-500/30"><i class="fas fa-plus mr-2"></i>Create</button>
                    <?php endif; ?>
                    <a href="index.php?logout=true" class="text-red-400 hover:text-red-300 p-2"><i class="fas fa-sign-out-alt text-xl"></i></a>
                <?php else: ?>
                    <button onclick="showSection('login')" class="text-white hover:text-accent font-medium">Login</button>
                    <button onclick="showSection('register')" class="bg-white text-navy px-5 py-2 rounded-full font-bold hover:bg-gray-100 transition">Register</button>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- NOTIFICATIONS -->
    <?php if(isset($error)): ?><div class="bg-red-500/90 text-white p-4 text-center backdrop-blur"><?php echo $error; ?></div><?php endif; ?>
    <?php if(isset($success)): ?><div class="bg-green-500/90 text-white p-4 text-center backdrop-blur"><?php echo $success; ?></div><?php endif; ?>

    <!-- MAIN CONTENT -->
    <div class="container mx-auto p-4 flex-grow relative z-10">

        <!-- DASHBOARD -->
        <div id="section-home" class="section">
            <!-- Hero Section -->
            <div class="text-center py-48 mb-12 hero-bg shadow-2xl flex flex-col justify-center min-h-[60vh]">
                <h1 class="text-6xl font-bold mb-6 drop-shadow-2xl">Events for <span class="text-accent">Everyone</span></h1>
                <p class="text-gray-200 text-xl max-w-2xl mx-auto font-light leading-relaxed drop-shadow-lg"><?php echo $current_role == 'host' ? 'Manage your events and guest lists with ease.' : 'Discover exclusive workshops, concerts, and parties tailored just for you.'; ?></p>
                <?php if(!isset($_SESSION['user_id'])): ?>
                    <div class="mt-8">
                        <button onclick="showSection('register')" class="bg-accent hover:bg-indigo-600 text-white px-8 py-3 rounded-full font-bold text-lg shadow-lg">Get Started</button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- SMART FEATURE: MY BOOKED TICKETS (Attendee Only) -->
            <?php if($current_role == 'attendee'): ?>
                <div class="mb-12">
                    <h2 class="text-2xl font-bold mb-6 border-l-4 border-green-500 pl-4">My Booked Tickets</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php if ($my_bookings && $my_bookings->num_rows > 0): ?>
                            <?php while($row = $my_bookings->fetch_assoc()): $imgSrc = getEventImage($row['category'], $row['subcategory'], $row['theme']); ?>
                                <div class="bg-lumina rounded-xl overflow-hidden shadow-xl border border-green-500/30">
                                    <div class="h-48 bg-gray-800 relative"><img src="<?php echo $imgSrc; ?>" class="w-full h-full object-cover"></div>
                                    <div class="p-4">
                                        <h3 class="font-bold text-xl"><?php echo $row['name']; ?></h3>
                                        <p class="text-gray-400 text-sm"><?php echo $row['venue']; ?></p>
                                        <div class="mt-2 text-green-400 font-bold text-sm">BOOKED <i class="fas fa-check"></i></div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="col-span-3 text-center py-10 bg-lumina/30 rounded-xl">No bookings yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- MY EVENTS (Host Only) -->
            <?php if($current_role == 'host'): ?>
                <div class="mb-12">
                    <h2 class="text-2xl font-bold mb-6 border-l-4 border-accent pl-4">My Events</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php if ($my_events && $my_events->num_rows > 0): ?>
                            <?php while($row = $my_events->fetch_assoc()): $imgSrc = getEventImage($row['category'], $row['subcategory'], $row['theme']); ?>
                                <div class="bg-lumina rounded-xl overflow-hidden shadow-xl border border-gray-700">
                                    <div class="h-48 bg-gray-800 relative"><img src="<?php echo $imgSrc; ?>" class="w-full h-full object-cover"></div>
                                    <div class="p-4">
                                        <h3 class="font-bold text-xl"><?php echo $row['name']; ?></h3>
                                        <div class="flex gap-2 mt-4 pt-4 border-t border-gray-700">
                                            <!-- PASSING HOST NAME TO MINI CARD FUNCTION -->
                                            <button onclick='showMiniCard(<?php echo json_encode($row); ?>, "<?php echo $imgSrc; ?>", "<?php echo $_SESSION['name']; ?>")' class="flex-1 bg-gray-700 text-xs py-2 rounded">Invite</button>
                                            <a href="index.php?delete=<?php echo $row['event_id']; ?>" class="bg-red-900/50 text-red-400 px-3 py-2 rounded"><i class="fas fa-trash"></i></a>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="col-span-3 text-center py-10 bg-lumina/30 rounded-xl">No events created.</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- PUBLIC EVENTS -->
            <div>
                <h2 class="text-2xl font-bold mb-6 border-l-4 border-blue-500 pl-4">Public Events</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php if ($public_events && $public_events->num_rows > 0): ?>
                        <?php while($row = $public_events->fetch_assoc()): $imgSrc = getEventImage($row['category'], $row['subcategory'], $row['theme']); ?>
                            <div class="bg-lumina rounded-xl overflow-hidden shadow-xl border border-gray-700 cursor-pointer" onclick='showPublicDetails(<?php echo json_encode($row); ?>, "<?php echo $imgSrc; ?>")'>
                                <div class="h-48 bg-gray-800 relative"><img src="<?php echo $imgSrc; ?>" class="w-full h-full object-cover"></div>
                                <div class="p-4">
                                    <h3 class="font-bold text-xl"><?php echo $row['name']; ?></h3>
                                    <div class="flex justify-between items-center pt-3 border-t border-gray-700">
                                        <span class="font-bold">Rs. <?php echo number_format($row['total_price'] / ($row['guests'] > 0 ? $row['guests'] : 1) * 1.2); ?></span>
                                        <span class="text-xs text-blue-400">Details <i class="fas fa-arrow-right"></i></span>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-span-3 text-center py-10 bg-lumina/30 rounded-xl">No public events.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- AUTH SECTION -->
        <div id="section-auth" class="section hidden max-w-md mx-auto mt-10">
            <div id="form-login" class="glass p-8 rounded-2xl shadow-2xl">
                <h2 class="text-3xl font-bold text-center mb-6">Welcome Back</h2>
                <form method="POST">
                    <input type="email" name="email" placeholder="Email" class="form-input" required>
                    <input type="password" name="password" placeholder="Password" class="form-input" required>
                    <button type="submit" name="login" class="w-full bg-accent text-white py-3 rounded-xl font-bold mt-4">Login</button>
                </form>
                <div class="mt-4 text-center"><button onclick="toggleAuth('register')" class="text-accent hover:underline">Register</button></div>
            </div>
            <div id="form-register" class="glass p-8 rounded-2xl shadow-2xl hidden">
                <h2 class="text-3xl font-bold text-center mb-6">Join Lumina</h2>
                <form method="POST">
                    <div class="mb-4 grid grid-cols-2 gap-4">
                        <label class="cursor-pointer bg-gray-800 p-3 rounded text-center"><input type="radio" name="role" value="host" checked> Host</label>
                        <label class="cursor-pointer bg-gray-800 p-3 rounded text-center"><input type="radio" name="role" value="attendee"> Attendee</label>
                    </div>
                    <input type="text" name="name" placeholder="Full Name" class="form-input" required>
                    <input type="email" name="email" placeholder="Email" class="form-input" required>
                    <input type="text" name="phone" placeholder="Phone" class="form-input" required>
                    <input type="password" name="password" placeholder="Password" class="form-input" required>
                    <button type="submit" name="register" class="w-full bg-accent text-white py-3 rounded-xl font-bold mt-4">Create Account</button>
                </form>
                <div class="mt-4 text-center"><button onclick="toggleAuth('login')" class="text-accent hover:underline">Login</button></div>
            </div>
        </div>

        <div id="section-create" class="section hidden max-w-4xl mx-auto">
            <div class="glass p-8 rounded-2xl shadow-2xl">
                <div class="flex justify-between items-center mb-6"><h2 class="text-2xl font-bold">Create Event</h2><button onclick="showSection('home')"><i class="fas fa-times"></i></button></div>
                <form method="POST" id="createForm" oninput="calculateTotal()">
                    <input type="hidden" name="total_price_hidden" id="total_price_hidden">
                    <input type="hidden" name="theme_name" id="theme_name_hidden">
                    
                    <div class="grid grid-cols-2 gap-6 mb-4">
                        <input type="text" name="event_name" placeholder="Title" class="form-input" required>
                        <select name="audience" class="form-input"><option value="personal">Personal</option><option value="public">Public</option></select>
                    </div>

                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <select name="category" id="catSelect" class="form-input" onchange="updateForm()" required><option value="">Category</option><option value="wedding">Wedding</option><option value="concert">Concert</option><option value="birthday">Birthday</option><option value="corporate">Corporate</option></select>
                        <select name="subcategory" id="subSelect" class="form-input" disabled><option>Sub-Category</option></select>
                        <select id="themeSelect" class="form-input" onchange="calculateTotal()" disabled><option value="0">Theme</option></select>
                    </div>

                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <select name="province" id="provSelect" class="form-input" onchange="updateCities()"><option>Province</option><option>Punjab</option><option>Sindh</option><option>KPK</option><option>Balochistan</option><option>Islamabad</option><option>Gilgit Baltistan</option><option>Azad Kashmir</option></select>
                        <select name="city" id="citySelect" class="form-input" onchange="updateVenues()" disabled><option>City</option></select>
                        <select name="venue" id="venueSelect" class="form-input" disabled><option>Venue</option></select>
                    </div>

                    <div id="guestSection" class="hidden mb-4 p-4 bg-gray-800 rounded">
                        <h3 class="font-bold mb-2">Special Guests</h3><div id="guestContainer" class="grid grid-cols-2 gap-4"></div>
                    </div>
                    <div id="foodSection" class="hidden mb-4 p-4 bg-gray-800 rounded">
                        <h3 class="font-bold mb-2">Food Menu</h3><div id="foodTabs" class="flex gap-2 mb-2"></div><div id="foodContainer" class="grid grid-cols-3 gap-2"></div>
                    </div>

                    <!-- UPDATED: Added Time Input -->
                    <div class="grid grid-cols-3 gap-4 mb-6">
                        <div class="col-span-1"><label class="text-xs font-bold text-accent mb-2 block">No. of Guests</label><input type="number" name="guests" id="guestsInput" placeholder="Guests" class="form-input" required></div>
                        <div class="col-span-1"><label class="text-xs font-bold text-accent mb-2 block">Date</label><input type="date" name="date" class="form-input" required></div>
                        <div class="col-span-1"><label class="text-xs font-bold text-accent mb-2 block">Time</label><input type="time" name="time" class="form-input" required></div>
                    </div>
                    
                    <div class="bg-gray-800 p-4 rounded text-center mb-6">
                        <p class="text-sm">Total Cost</p><h2 class="text-3xl font-bold" id="displayTotal">Rs. 0</h2>
                    </div>
                    <button type="submit" name="create_event" class="w-full bg-accent text-white py-4 rounded-xl font-bold">Create Event</button>
                </form>
            </div>
        </div>
    </div>

    <!-- MODALS -->
    <!-- UPDATED: Mini Card Modal with Prominent Close Button -->
    <div id="miniCardModal" class="fixed inset-0 bg-black/90 hidden flex items-center justify-center p-4 z-50">
        <div class="bg-white text-navy w-full max-w-sm rounded-xl overflow-hidden relative shadow-2xl">
            <!-- New Bright Close Button -->
            <button onclick="document.getElementById('miniCardModal').classList.add('hidden')" class="absolute top-2 right-2 bg-white/90 rounded-full p-2 text-red-600 hover:bg-red-100 z-50 shadow-md transition transform hover:scale-110">
                <i class="fas fa-times text-lg"></i>
            </button>
            <div id="cardContent"></div>
        </div>
    </div>

    <div id="publicDetailModal" class="fixed inset-0 bg-black/90 hidden flex items-center justify-center p-4 z-50">
        <div class="bg-white text-navy w-full max-w-md rounded-2xl overflow-hidden relative shadow-2xl">
            <!-- New Bright Close Button -->
            <button onclick="document.getElementById('publicDetailModal').classList.add('hidden')" class="absolute top-2 right-2 bg-white/90 rounded-full p-2 text-red-600 hover:bg-red-100 z-50 shadow-md transition transform hover:scale-110">
                <i class="fas fa-times text-lg"></i>
            </button>
            <div id="publicDetailContent"></div>
        </div>
    </div>

    <!-- BOOKING SUCCESS MODAL -->
    <div id="bookingModal" class="fixed inset-0 bg-black/90 hidden flex items-center justify-center p-4 z-50 backdrop-blur-sm">
        <div class="bg-white text-navy w-full max-w-sm rounded-xl overflow-hidden shadow-2xl p-6 text-center">
            <div class="w-16 h-16 bg-green-100 text-green-500 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-check text-2xl"></i></div>
            <h2 class="text-2xl font-bold mb-2">Booking Confirmed!</h2>
            <p class="text-gray-500 mb-6">You have successfully booked tickets for <span id="bookedEventName" class="font-bold text-navy"></span>.</p>
            <button onclick="document.getElementById('bookingModal').classList.add('hidden')" class="bg-navy text-white px-6 py-2 rounded-lg font-bold">Close</button>
        </div>
    </div>

    <footer class="bg-slate-900 border-t border-gray-700 mt-12 py-8 relative z-10">
        <div class="container mx-auto px-4 text-center">
            <p class="text-gray-500 text-xs">&copy; <?php echo date('Y'); ?> Lumina Event Management.</p>
        </div>
    </footer>

    <script>
        const LOCATIONS = { 
            "Punjab": ["Lahore", "Faisalabad", "Rawalpindi", "Multan", "Gujranwala", "Sialkot", "Bahawalpur", "Sargodha", "Gujarat", "Sheikhupura", "Jhelum", "Rahim Yar Khan"],
            "Sindh": ["Karachi", "Hyderabad", "Sukkur", "Larkana", "Nawabshah", "Mirpur Khas", "Jacobabad", "Shikarpur", "Khairpur", "Dadu", "Thatta", "Badin"],
            "KPK": ["Peshawar", "Mardan", "Abbottabad", "Swat", "Kohat", "Dera Ismail Khan", "Bannu", "Mansehra", "Charsadda", "Nowshera", "Chitral", "Haripur"],
            "Balochistan": ["Quetta", "Gwadar", "Ziarat", "Turbat", "Khuzdar", "Chaman", "Hub", "Sibi", "Loralai", "Zhob", "Kalat", "Mastung"],
            "Islamabad": ["Islamabad City", "Rawal Town", "Margalla Town", "F-Sector", "G-Sector", "I-Sector", "Bahria Enclave", "DHA", "Bani Gala", "Chak Shahzad"],
            "Gilgit Baltistan": ["Gilgit", "Skardu", "Hunza", "Ghizer", "Diamer", "Astore", "Ghanche", "Kharmang", "Shigar", "Nagar"],
            "Azad Kashmir": ["Muzaffarabad", "Mirpur", "Kotli", "Rawalakot", "Bagh", "Bhimber", "Sudhanoti", "Neelum", "Haveli", "Hattian"]
        };
        
        const VENUES_MASTER = [
            "Pearl Continental", "Serena Hotel", "Marriott Hotel", "Avari Towers", "Movenpick Hotel", "Ramada Plaza", 
            "Luxus Grand", "Nishat Hotel", "Heritage Luxury Suites", "Garrison Golf Club", "Royal Palm Golf & Country Club", 
            "City Banquet Hall", "Grand Marquee", "Crystal Ballroom", "The Monal", "Haveli Restaurant", "Salt'n Pepper Village",
            "Gymkhana Club", "Defense Club", "Officer's Mess"
        ];

        const CATALOG = {
            wedding: {
                subs: ['Barat', 'Walima', 'Mehndi', 'Nikkah', 'Mayun', 'Dholki', 'Bridal Shower', 'Engagement'],
                themes: [{name: 'Royal Red & Gold', p: 50000}, {name: 'Floral Pastel', p: 45000}, {name: 'Vibrant Yellow', p: 30000}, {name: 'Traditional Orange', p: 25000}, {name: 'Elegant White', p: 35000}],
                food: { 
                    "Starters": [{n: 'Hot & Sour Soup', p: 300, i:'soup_hot.jpg'}, {n: 'Corn Soup', p: 300, i:'soup_corn.jpg'}, {n: 'Finger Fish', p: 600, i:'fish_finger.jpg'}, {n: 'Wings', p: 450, i:'wings.jpg'}],
                    "Desi Rice": [{n: 'Chicken Biryani', p: 500, i:'desi_biryani_chicken.jpg'}, {n: 'Mutton Biryani', p: 800, i:'desi_biryani_mutton.jpg'}, {n: 'Beef Pulao', p: 600, i:'desi_pulao_beef.jpg'}, {n: 'Chicken Pulao', p: 550, i:'desi_pulao_chicken.jpg'}],
                    "Desi Salan": [{n: 'Chicken Karahi', p: 700, i:'desi_karahi_chicken.jpg'}, {n: 'Mutton Karahi', p: 1200, i:'desi_karahi_mutton.jpg'}, {n: 'Qorma', p: 750, i:'desi_korma.jpg'}, {n: 'Nihari', p: 800, i:'desi_nihari.jpg'}],
                    "Chinese": [{n: 'Fried Rice', p: 400, i:'chinese_rice_egg.jpg'}, {n: 'Manchurian', p: 500, i:'chinese_gravy_manchurian.jpg'}, {n: 'Chowmein', p: 450, i:'chinese_chowmein.jpg'}],
                    // Renamed Gajar Halwa to Gulaab Jamun here
                    "Desserts": [{n: 'Gulaab Jamun', p: 300, i:'dessert_gulabjamun.jpg'}, {n: 'Kheer', p: 250, i:'dessert_kheer.jpg'}, {n: 'Kulfa', p: 200, i:'dessert_icecream.jpg'}]
                },
                guests: [
                    {n: 'Famous DJ', role: 'Music', p: 150000, i: 'ent_dj.jpg'},
                    {n: 'Event Photographer', role: 'Media', p: 250000, i: 'ent_photographer.jpg'},
                    {n: 'Wedding Planner', role: 'Organizer', p: 300000, i: 'speaker_ceo.jpg'},
                    {n: 'Makeup Artist', role: 'Beauty', p: 100000, i: 'ent_makeup.jpg'}
                ]
            },
            concert: {
                subs: ['Pop Night', 'Qawali Night', 'Rock Fest', 'Sufi Night', 'EDM Festival', 'Classical Night'],
                themes: [{name: 'Neon Blast', p: 30000}, {name: 'Sufi Mystical', p: 25000}, {name: 'Dark Rock', p: 20000}, {name: 'Open Air', p: 15000}],
                food: { 
                    "Fast Food": [{n: 'Zinger Burger', p: 450, i:'burger_beef.jpg'}, {n: 'Club Sandwich', p: 400, i:'sandwich_club.jpg'}, {n: 'Pizza', p: 300, i:'italian_pizza_fajita.jpg'}, {n: 'Fries', p: 200, i:'food_fries.jpg'}], 
                    "Drinks": [{n: 'Soda', p: 100, i:'food_soda.jpg'}, {n: 'Water', p: 50, i:'food_water.jpg'}, {n: 'Coffee', p: 250, i:'food_coffee.jpg'}] 
                },
                guests: [
                    {n: 'Atif Aslam', role: 'Singer', p: 1700000, i: 'singer_atif.jpg'},
                    {n: 'Ali Zafar', role: 'Singer', p: 1400000, i: 'singer_ali.jpg'},
                    {n: 'Young Stunners', role: 'Band', p: 1000000, i: 'singer_young_stunners.jpg'},
                    {n: 'Rahat Fateh Ali Khan', role: 'Qawwal', p: 1500000, i: 'singer_rahat.jpg'},
                    {n: 'Aima Baig', role: 'Singer', p: 1200000, i: 'singer_aima.jpg'},
                    {n: 'Arijit Singh (Virtual)', role: 'Singer', p: 2000000, i: 'singer_arjit.jpg'}
                ]
            },
            birthday: {
                subs: ['Kids Party', 'Sweet 16', 'Surprise Party', 'Milestone', 'Garden Party'],
                themes: [{name: 'Jungle Safari', p: 15000}, {name: 'Princess', p: 15000}, {name: 'Superheroes', p: 15000}, {name: 'Black & Gold', p: 20000}],
                food: { "Kids": [{n: 'Nuggets', p: 300, i:'wings.jpg'}, {n: 'Mini Burger', p: 350, i:'burger_beef.jpg'}, {n: 'Mac n Cheese', p: 400, i:'italian_pasta_alfredo.jpg'}], "Sweets": [{n: 'Cake', p: 250, i:'dessert_cake.jpg'}, {n: 'Ice Cream', p: 150, i:'dessert_icecream.jpg'}, {n: 'Brownie', p: 200, i:'dessert_brownie.jpg'}] },
                guests: [
                    {n: 'Funny Clown', role: 'Entertainer', p: 100000, i: 'ent_clown.jpg'},
                    {n: 'Magician', role: 'Entertainer', p: 150000, i: 'ent_magician.jpg'},
                    {n: 'Face Painter', role: 'Art', p: 50000, i: 'ent_facepainter.jpg'}
                ]
            },
            corporate: {
                subs: ['Seminar', 'Annual Dinner', 'Launch Event', 'Team Building', 'Award Ceremony'],
                themes: [{name: 'Corporate Blue', p: 10000}, {name: 'Minimalist Tech', p: 15000}, {name: 'Formal Black Tie', p: 20000}],
                food: { "Hi-Tea": [{n: 'Patties', p: 150, i:'food_patties.jpg'}, {n: 'Tea/Coffee', p: 100, i:'food_tea.jpg'}], "Lunch": [{n: 'Chicken Handi', p: 600, i:'desi_karahi_chicken.jpg'}, {n: 'Rice', p: 300, i:'chinese_rice_egg.jpg'}] },
                guests: [
                    {n: 'Motivational Speaker', role: 'Speaker', p: 250000, i: 'speaker_motivational.jpg'},
                    {n: 'Industry CEO', role: 'Guest', p: 100000, i: 'speaker_ceo.jpg'},
                    {n: 'Tech Guru', role: 'Expert', p: 200000, i: 'speaker_motivational.jpg'}
                ]
            }
        };

        function showSection(id) {
            document.querySelectorAll('.section').forEach(el => el.classList.add('hidden'));
            if(id === 'login' || id === 'register') { document.getElementById('section-auth').classList.remove('hidden'); toggleAuth(id); }
            else { document.getElementById('section-' + id).classList.remove('hidden'); }
            window.scrollTo(0,0);
        }
        function toggleAuth(type) {
            document.getElementById('form-login').classList.toggle('hidden', type !== 'login');
            document.getElementById('form-register').classList.toggle('hidden', type !== 'register');
        }
        function updateCities() {
            const p = document.getElementById('provSelect').value; const c = document.getElementById('citySelect'); const v = document.getElementById('venueSelect');
            c.innerHTML = '<option>City</option>'; v.innerHTML = '<option>Venue</option>'; v.disabled = true;
            if(p && LOCATIONS[p]) { c.disabled = false; LOCATIONS[p].forEach(ct => c.innerHTML += `<option>${ct}</option>`); } else { c.disabled = true; }
        }
        
        // --- SMART VENUE LOGIC ---
        function updateVenues() {
            const c = document.getElementById('citySelect').value; const v = document.getElementById('venueSelect');
            v.innerHTML = '<option>Venue</option>';
            if(c !== 'City') { 
                v.disabled = false; 
                // Generates venues like "Pearl Continental Lahore", "Serena Hotel Lahore"
                VENUES_MASTER.forEach(vn => v.innerHTML += `<option>${vn} ${c}</option>`); 
            } else { v.disabled = true; }
        }

        // NEW: Booking Confirmation Function
        function confirmBooking(eventName) {
            document.getElementById('bookedEventName').innerText = eventName;
            document.getElementById('publicDetailModal').classList.add('hidden'); 
            document.getElementById('bookingModal').classList.remove('hidden');
        }

        function updateForm() {
            const cat = document.getElementById('catSelect').value; const sub = document.getElementById('subSelect'); const thm = document.getElementById('themeSelect');
            const fs = document.getElementById('foodSection'); const gs = document.getElementById('guestSection');
            sub.innerHTML = '<option>Sub-Category</option>'; sub.disabled = true; thm.disabled = true; fs.classList.add('hidden'); gs.classList.add('hidden');
            if(cat && CATALOG[cat]) {
                sub.disabled = false; CATALOG[cat].subs.forEach(s => sub.innerHTML += `<option>${s}</option>`);
                thm.disabled = false; thm.innerHTML = '<option value="0">Theme</option>'; CATALOG[cat].themes.forEach(t => thm.innerHTML += `<option value="${t.p}" data-name="${t.name}">${t.name} (+${t.p})</option>`);
                
                // Guests
                if(CATALOG[cat].guests) {
                    gs.classList.remove('hidden'); document.getElementById('guestContainer').innerHTML = CATALOG[cat].guests.map(g => 
                        `<label class="flex items-center gap-2 bg-gray-700 p-2 rounded"><input type="checkbox" name="special_guests[]" value="${g.n}" data-price="${g.p}" onchange="calculateTotal()"> <img src="assets/${g.i}" class="w-10 h-10 rounded-full object-cover"> <div><div class="font-bold text-sm">${g.n}</div><div class="text-xs text-gold">${g.role} • Rs.${g.p.toLocaleString()}</div></div></label>`
                    ).join('');
                }
                // Food
                if(CATALOG[cat].food) {
                    fs.classList.remove('hidden'); const tabs = document.getElementById('foodTabs'); const cont = document.getElementById('foodContainer');
                    tabs.innerHTML = Object.keys(CATALOG[cat].food).map(k => `<button type="button" onclick="showFood('${k}')" class="px-3 py-1 bg-gray-600 rounded text-xs mr-2 hover:bg-gray-500">${k}</button>`).join('');
                    showFood(Object.keys(CATALOG[cat].food)[0]);
                }
            }
        }
        function showFood(grp) {
            const cat = document.getElementById('catSelect').value;
            document.getElementById('foodContainer').innerHTML = CATALOG[cat].food[grp].map(f => 
                `<label class="flex items-center gap-2 bg-gray-700 p-2 rounded"><input type="checkbox" name="food_items[]" value="${f.n}" data-price="${f.p}" onchange="calculateTotal()"> <img src="assets/${f.i}" class="w-8 h-8 rounded object-cover"> ${f.n}</label>`
            ).join('');
        }
        function calculateTotal() {
            const guests = parseInt(document.getElementById('guestsInput').value) || 0;
            const themeCost = parseInt(document.getElementById('themeSelect').value) || 0;
            const thmSel = document.getElementById('themeSelect');
            if(thmSel.selectedIndex > 0) document.getElementById('theme_name_hidden').value = thmSel.options[thmSel.selectedIndex].getAttribute('data-name');
            let foodCost = 0; document.querySelectorAll('input[name="food_items[]"]:checked').forEach(cb => foodCost += parseInt(cb.dataset.price));
            let guestFees = 0; document.querySelectorAll('input[name="special_guests[]"]:checked').forEach(cb => guestFees += parseInt(cb.dataset.price));
            const total = (foodCost * guests) + themeCost + guestFees;
            document.getElementById('displayTotal').innerText = "Rs. " + total.toLocaleString();
            document.getElementById('total_price_hidden').value = total;
        }

        // Updated function to accept hostName
        function showMiniCard(evt, imgSrc, hostName = 'Host') {
            const c = document.getElementById('cardContent');
            c.innerHTML = `
                <div class="relative h-48"><img src="${imgSrc}" class="w-full h-full object-cover" onerror="this.src='assets/party.jpg'"><div class="absolute inset-0 bg-black/20"></div></div>
                <div class="p-6 text-center">
                    <p class="text-gold font-serif italic">You are invited to</p>
                    <h2 class="text-2xl font-bold text-navy uppercase">${evt.subcategory}</h2>
                    <h3 class="text-gray-500 mb-4">${evt.name}</h3>
                    <div class="text-sm text-gray-600 space-y-2 mb-4">
                        <div><i class="far fa-calendar text-gold mr-2"></i>${evt.date}</div>
                        <div><i class="fas fa-map-marker-alt text-gold mr-2"></i>${evt.venue}, ${evt.city}</div>
                        <!-- Added Time Display -->
                        <div><i class="far fa-clock text-gold mr-2"></i>${evt.time ? evt.time : 'Time not specified'}</div>
                    </div>
                    <div class="border-t border-gray-200 pt-3 mt-4">
                        <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide">Hosted by</p>
                        <p class="text-navy font-bold text-lg">${hostName}</p>
                    </div>
                </div>`;
            document.getElementById('miniCardModal').classList.remove('hidden');
        }

        function showPublicDetails(evt, imgSrc) {
            const modal = document.getElementById('publicDetailModal');
            const content = document.getElementById('publicDetailContent');
            const price = Math.round(evt.total_price / (evt.guests > 0 ? evt.guests : 1) * 1.2);
            
            // SMART LOGIC: Show Booking Form for Attendees Only
            let btnHtml = '';
            <?php if($current_role == 'attendee'): ?>
                btnHtml = `
                    <form method="POST">
                        <input type="hidden" name="event_id" value="${evt.event_id}">
                        <button type="submit" name="book_ticket" class="w-full bg-accent hover:bg-indigo-600 text-white py-3 rounded-xl font-bold shadow-lg shadow-indigo-500/30 transition">
                            Confirm Booking
                        </button>
                    </form>`;
            <?php elseif($current_role == 'host'): ?>
                btnHtml = '<div class="text-center text-gray-400 text-sm">Hosts cannot book tickets.</div>';
            <?php else: ?>
                btnHtml = '<button onclick="showSection(\'login\'); document.getElementById(\'publicDetailModal\').classList.add(\'hidden\')" class="w-full bg-navy hover:bg-gray-800 text-white py-3 rounded-xl font-bold transition">Login to Book</button>';
            <?php endif; ?>

            content.innerHTML = `
                <div class="relative h-56">
                    <img src="${imgSrc}" class="w-full h-full object-cover" onerror="this.src='assets/party.jpg'">
                    <div class="absolute inset-0 bg-gradient-to-t from-navy to-transparent"></div>
                    <div class="absolute bottom-4 left-6">
                        <span class="bg-accent px-2 py-1 rounded text-xs font-bold text-white mb-2 inline-block">${evt.subcategory}</span>
                        <h2 class="text-3xl font-bold text-white">${evt.name}</h2>
                    </div>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="bg-gray-100 p-3 rounded-lg text-navy text-center"><i class="far fa-calendar text-accent mb-1 block"></i><span class="text-sm font-bold">${evt.date}</span></div>
                        <div class="bg-gray-100 p-3 rounded-lg text-navy text-center"><i class="fas fa-map-marker-alt text-accent mb-1 block"></i><span class="text-sm font-bold">${evt.city}</span></div>
                    </div>
                    <div class="space-y-2 text-gray-600 mb-6 text-sm">
                        <div class="flex justify-between border-b pb-2"><span>Venue</span> <span class="font-bold text-navy">${evt.venue}</span></div>
                        <div class="flex justify-between border-b pb-2"><span>Theme</span> <span class="font-bold text-navy">${evt.theme}</span></div>
                        <div class="flex justify-between border-b pb-2"><span>Time</span> <span class="font-bold text-navy">${evt.time ? evt.time : 'N/A'}</span></div>
                        <div class="flex justify-between items-center pt-2"><span>Ticket Price</span> <span class="text-2xl font-bold text-accent">Rs. ${price}</span></div>
                    </div>
                    ${btnHtml}
                </div>
            `;
            modal.classList.remove('hidden');
        }

        window.onload = function() { const d = document.querySelector('input[type="date"]'); if(d) d.min = new Date().toISOString().split('T')[0]; };
    </script>
</body>
</html>