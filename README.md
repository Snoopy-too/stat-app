# Board Game Club StatApp
#### Video Demo:  https://youtu.be/5Dpl84pAUo8
#### Description:

The Board Game Club StatApp is a pretty cool web application designed to manage and track statistics for board game clubs. Basically, the app allows users to keep track of results for board games they play. Organized into "clubs", users can have up to 5 clubs with different members in each. The front end does not require login to view the stats and history of clubs. Only the club administer needs to login to manage their clubs, members, teams, game library, game play results and more. The app is designed to be user-friendly and intuitive, with a clean and modern interface. 

The following sections break down the key components and their functionalities for both technical and non-technical users.

## 1. Admin Area Features

- `admin/add_result.php`: Record new individual game results for a club's game.
- `admin/add_team_result.php`: Record new team-based game results for a club's game.
- `admin/change_password.php`: Change administrator account password.
- `admin/club_teams.php`: Manage and view all teams for a particular club.
- `admin/dashboard.php`: Main admin dashboard showing high-level statistics and shortcuts.
- `admin/delete_team.php`: Remove a team from a club.
- `admin/edit_club.php`: Edit club name.
- `admin/edit_game.php`: Edit the details of a game in the library.
- `admin/edit_member.php`: Edit member profiles and details.
- `admin/edit_result.php`: Edit existing individual game results.
- `admin/edit_team.php`: Edit team information for a club.
- `admin/edit_team_result.php`: Edit an existing team game result.
- `admin/game_details.php`: Detailed administrative view for a specific game and its settings.
- `admin/login.php`: Admin login page.
- `admin/logout.php`: Logout endpoint for admins.
- `admin/manage_champions.php`: Manage club championship records and champions.
- `admin/manage_clubs.php`: Create, edit, and remove clubs (admin level).
- `admin/manage_games.php`: Add, edit, and remove games in the system.
- `admin/manage_logo.php`: Manage club or system logos.
- `admin/manage_members.php`: Manage (add, search, update) members of clubs.
- `admin/manage_trophy.php`: Update or replace club trophy images.
- `admin/member_profile.php`: View and update individual member statistics and achievements.
- `admin/profile.php`: View and update profile details for the currently logged-in admin.
- `admin/results.php`: List and manage results for a game (individual and team) for each club.
- `admin/statistics.php`: View overall, club, and game-based statistical summaries.
- `admin/view_club.php`: Full administrative detail page for a club.
- `admin/view_result.php`: Detailed page for individual game results.
- `admin/view_team_result.php`: Detailed page for team game results.
- `settings.php`: Application-wide and user-specific settings.

## 2. Frontend/Visitor Features

### Core and Navigation
- `index.php`: Main entry point, landing page, navigation.
- `dashboard.php`: Overview statistics, recent activities, quick access features.
- `auth.php`: Authentication logic and session management.
- `login.php`: User login process.
- `register.php`: New user registration with email verification.
- `verify_email.php`: Email verification for registrations.
- `search_clubs.php`: Search and find different board game clubs.

### Game Management
- `games.php`: Display, browse, and search the game library.
- `game_details.php`: Detailed information, play history, and statistics for each game.
- `game_play.php`: Record new game sessions and outcomes.
- `game_play_details.php`: Detailed game session information.
- `game_days.php`: Management and scheduling of special game day events.
- `game_days_results.php`: Record and display results from game day events.

### User/Member Features
- `members.php`: Member profiles and gaming statistics.
- `member_stathistory.php`: Individual member statistics over time.
- `teams.php`: Team creation and membership management.
- `team_game_play_details.php`: Team-based game sessions and result details.

### Club Features
- `club_stats.php`: Club statistics, champions, and member lists.
- `club_game_list.php`: List of games within a club.
- `club_game_results.php`: Display club-specific game results.

### Statistics and History
- `champions.php`: Championship records and achievements.
- `history.php`: Full history of sessions and outcomes.
- `view_stats.php`: Statistical analysis and report generation.

### Configuration, SQL, and Support Files
- `config/`: Database and application configuration.
- `sql/`: Supplementary SQL migration and setup scripts.
- `process_registration.php`: Handles backend logic for processing member/user registrations.
- `success.php`: Shown on successful registration or action.

### Super Admin Panel
- `super_admin/login.php`: Super admin login screen.
- `super_admin/logout.php`: Super admin logout endpoint.
- `super_admin/super_admin_cp.php`: Super admin control panel.

### Additional Backend Utilities
- `includes/RegistrationHandler.php`: Registration processing.
- `includes/SecurityUtils.php`: Security and validation stuff.

### JavaScript
- `js/register.js`: Registration form interactivity, validation, or UX.
- `js/team-validation.js`: JavaScript for team feature validation or dynamic handling.

### Assets, Styling, and Media
- `images/`: Game images, avatars, club logos, trophies, placeholders.
- `uploads/`: Directory for user or admin-uploaded content (e.g., logos).
- `logs/`: Location for system- or app-generated logs for debugging, error tracking.

## 3. Back story for the web app

I have been playing board and card games once a month with the same three friends for over 8 years. More often than not, we'd open a board game up to play and try to remember who won this particular game last time we got together. There were times when we'd argue that we hadn't played a game in a while but actually we'd just forgotten that we played it last time. Having more than 20 years of (self-taught) experience with PHP, MySQL and HTML, I decided to create a web app that would help me keep track of all the games that we played. Unfortunately, I had been unable to keep up with all the changes and new ways to write code, as well as having not much knowledge of JavaScript. But instead of embarking on a journey with another "For Dummies" book in hand, I decided to put my ambition on hold and take CS50x to shore up my skills as well as to learn the basics of computer science as a whole. After nearly 4 months and then another month or so working on this web app as my Final Project, I am proud to present you with the Board Game Club StatApp. It may not change the world, but it solves problems for board gamers who take their games seriously and it will outlive the duration of CS50x and probably the next CS50 course I am planning to take. 

While CS50x gave me some new, unfamiliar tools such as Python and C, I felt more comfortable using PHP, MySQL and JavaScript for this project, as it's what I've used for years. But CS50x taught me better practices with regard to design and architecture, and I learned how to use MySQL queries that I had never thought of before. But most importantly, CS50x gave me a different way to approach problem solving and a different way to think about problems themselves.

Thank you to David Malan for his amazing CS50 course and to all the instructors and staff who helped me along the way. I hope you enjoy using the Board Game Club StatApp as much as I did creating it!