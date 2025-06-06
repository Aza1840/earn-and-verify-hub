
# Earn and Verify Hub - PHP Version

A complete earning platform where users can earn money by watching videos and reading news articles.

## Features

- **User Registration & Authentication**
  - Email verification system
  - Profile management with picture upload
  - Referral system with commission tracking

- **Video Earning System**
  - Admin can upload video thumbnails
  - Videos redirect to external links
  - Verification code system for earning rewards
  - Track watched videos per user

- **News Earning System**
  - Time-based reading rewards
  - Social sharing functionality
  - Admin configurable reading time requirements

- **Referral System**
  - Commission on referral activities
  - Email verification requirement for commissions
  - Premium referral bonuses

- **Premium Accounts**
  - Upgrade through money deposits
  - Enhanced earning opportunities

- **Admin Panel**
  - Manage videos, news, and users
  - Configure system settings
  - View earnings and statistics

## Installation Instructions

### 1. Upload Files
- Extract all PHP files to your cPanel public_html directory
- Ensure the uploads folder has write permissions (755 or 777)

### 2. Database Setup
- Create a MySQL database in cPanel
- Import the `database/schema.sql` file through phpMyAdmin
- Update database credentials in `config/database.php`

### 3. Configuration
- Update `config/config.php` with your domain and SMTP settings
- Set up email configuration for verification emails

### 4. File Permissions
Make sure these directories are writable:
```
uploads/
uploads/thumbnails/
uploads/profiles/
uploads/news/
```

### 5. Admin Access
Default admin login:
- Email: admin@example.com
- Password: admin123

**Change this immediately after installation!**

## File Structure

```
/
├── config/
│   ├── config.php          # Main configuration
│   └── database.php        # Database connection
├── includes/
│   └── functions.php       # Core functions
├── admin/                  # Admin panel files
├── uploads/               # File uploads directory
├── database/
│   └── schema.sql         # Database structure
├── index.php              # Homepage
├── login.php              # User login
├── register.php           # User registration
├── dashboard.php          # User dashboard
├── videos.php             # Videos listing
├── watch-video.php        # Video watching page
├── news.php               # News listing
├── read-news.php          # News reading page
└── profile.php            # User profile
```

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- cPanel hosting with file upload support
- SMTP email service (optional but recommended)

## Security Notes

- Change default admin credentials immediately
- Configure proper file upload restrictions
- Use HTTPS in production
- Regular backup of database and files
- Update SMTP credentials for email functionality

## Support

For issues or questions:
1. Check file permissions
2. Verify database connection
3. Check error logs in cPanel
4. Ensure all configuration values are correct

## License

This script is provided as-is for educational and commercial use.
