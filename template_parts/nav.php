<nav class="navbar navbar-light pt-4 pb-4" id="navbar">
    <div class="container">
        <a class="navbar-brand" href="/eduroam/management.php"><?php echo $site_name; ?> Management</a>
        <?php 
            if(isset($_SESSION['user'])) {
        ?>
                <?php
            $userDetails = $_SESSION['user'];
            // Check if the user is logged in
            if(isset($userDetails['username'])) {
                // Extract the username
                $username = $userDetails['username'];
                $email = $userDetails['email'];
                $fullname = $userDetails['fullname'];
                // Display the "You are logged in as" message
                echo "<span class='text-white mt-4 mb-4'>You are logged in as: $fullname ($email)</span>";
            }
        ?>
        <form action="logout.php" method="post" class="d-flex">
            <button type="submit" class="btn btn-danger">Logout</button>
        </form>
        <?php
            }
        ?>
    </div>
</nav>
