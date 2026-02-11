# S3 Digital - Digital Product Selling Website

A comprehensive digital product marketplace built with PHP, MySQL, and MDBootstrap UI. Features responsive mobile-first design, secure payment processing, and complete admin dashboard.

## 🚀 Features

### User Features
- **Authentication**: Secure user registration, login, and profile management
- **Product Browsing**: Advanced search, filtering, and categorization
- **Shopping Cart**: Add to cart, quantity management, coupon support
- **Secure Checkout**: Multiple payment gateways (Razorpay, Stripe, PayPal)
- **Order Management**: View order history, download purchased products
- **Responsive Design**: Native mobile app experience + professional desktop view

### Admin Features
- **Dashboard**: Real-time statistics, charts, and analytics
- **Product Management**: Add/edit/delete products with file uploads
- **Order Management**: View all orders, manage refunds, track payments
- **User Management**: View registered users, manage access
- **Coupon System**: Create discount codes with usage limits
- **Support System**: Manage customer tickets and FAQ
- **Settings**: Configure payment gateways, taxes, and site settings

## 🛠 Technology Stack

- **Frontend**: PHP, MDBootstrap UI, Font Awesome
- **Backend**: PHP 8+, MySQL 8+
- **Payment Gateways**: Razorpay, Stripe, PayPal
- **Security**: Password hashing, SQL injection prevention, XSS protection
- **Responsive**: Mobile-first design with native app feel

## 📁 Project Structure

```
S3 Digital/
├── admin/                  # Admin panel
│   ├── index.php          # Admin dashboard
│   ├── login.php          # Admin login
│   ├── products.php       # Product management
│   ├── orders.php         # Order management
│   ├── users.php          # User management
│   ├── categories.php     # Category management
│   ├── coupons.php        # Coupon management
│   ├── support.php        # Support tickets
│   ├── reports.php        # Reports & analytics
│   └── settings.php       # System settings
├── api/                   # API endpoints
│   ├── add_to_cart.php    # Cart operations
│   ├── apply_coupon.php   # Coupon validation
│   ├── create_stripe_session.php # Payment processing
│   ├── get_order_details.php    # Order details
│   └── get_downloads.php  # Download management
├── assets/                # Static assets
│   ├── css/
│   │   └── style.css      # Custom styles
│   ├── js/
│   │   └── script.js      # Custom JavaScript
│   └── images/            # Product images
├── uploads/               # File uploads
│   ├── products/          # Product files
│   └── screenshots/       # Product screenshots
├── config.php             # Database and site configuration
├── functions.php          # Utility functions
├── database.sql           # Database schema
├── index.php              # Homepage
├── products.php           # Product listing
├── product.php            # Product details
├── cart.php               # Shopping cart
├── checkout.php           # Checkout process
├── payment.php            # Payment processing
├── order_success.php      # Order confirmation
├── download.php           # File downloads
├── orders.php             # User orders
├── register.php           # User registration
├── login.php              # User login
├── profile.php            # User profile
├── logout.php             # Logout
├── faq.php                # FAQ page
├── contact.php            # Contact page
└── README.md              # This file
```

## 🚀 Installation

### Prerequisites
- PHP 8.0 or higher
- MySQL 8.0 or higher
- Apache/Nginx web server
- Composer (for payment gateway dependencies)

### Step 1: Database Setup
1. Create a new MySQL database named `s3_digital`
2. Import the `database.sql` file to create all tables
3. Update database credentials in `config.php`

### Step 2: Configuration
1. Copy the project files to your web server directory
2. Update `config.php` with your database credentials
3. Configure payment gateway API keys in `config.php`:
   - Razorpay: Update `RAZORPAY_KEY` and `RAZORPAY_SECRET`
   - Stripe: Update `STRIPE_KEY` and `STRIPE_SECRET`
   - PayPal: Update `PAYPAL_CLIENT_ID` and `PAYPAL_CLIENT_SECRET`

### Step 3: File Permissions
Set write permissions for upload directories:
```bash
chmod 755 uploads/
chmod 755 uploads/products/
chmod 755 uploads/screenshots/
```

### Step 4: Payment Gateway Setup
1. **Razorpay**: Create account at razorpay.com and get API keys
2. **Stripe**: Create account at stripe.com and get API keys
3. **PayPal**: Create business account and get API credentials

### Step 5: Access the Application
- **Frontend**: `http://localhost/S3%20Digital/`
- **Admin Panel**: `http://localhost/S3%20Digital/admin/`
- **Default Admin Login**: Email: `admin@s3digital.com`, Password: `admin123`

## 🔐 Security Features

- **Password Security**: Argon2ID hashing with salt
- **SQL Injection Prevention**: Prepared statements for all database queries
- **XSS Protection**: Input sanitization and output escaping
- **CSRF Protection**: Token-based form validation
- **File Upload Security**: File type validation and size limits
- **Session Security**: Secure session configuration with HttpOnly cookies
- **Access Control**: Role-based permissions for admin features

## 📱 Responsive Design

### Mobile Experience
- Native app-like interface with bottom navigation
- Touch-friendly buttons and controls
- Optimized product cards and forms
- Swipe gestures and smooth animations

### Desktop Experience
- Professional website layout with sidebar navigation
- Advanced filtering and search capabilities
- Multi-column product grids
- Enhanced admin dashboard with charts

## 💳 Payment Integration

### Supported Gateways
1. **Razorpay**: Indian payment gateway supporting cards, UPI, wallets
2. **Stripe**: International payment gateway for global customers
3. **PayPal**: Popular payment platform with buyer protection

### Payment Flow
1. User adds products to cart
2. Proceeds to checkout with billing information
3. Selects payment method and enters payment details
4. Payment is processed securely via chosen gateway
5. Order is confirmed and download links are generated
6. User receives email confirmation and can download products

## 📊 Admin Dashboard Features

### Statistics Overview
- Total users, products, orders, and revenue
- Today's sales and recent activity
- Interactive charts for sales trends
- Top-selling products and customer insights

### Management Tools
- **Products**: Add/edit products with file uploads and screenshots
- **Orders**: View all orders, manage refunds, update status
- **Users**: Manage user accounts and permissions
- **Coupons**: Create promotional codes with restrictions
- **Support**: Handle customer tickets and FAQ management
- **Settings**: Configure site settings, payment gateways, taxes

## 🛒 E-commerce Features

### Product Management
- Multiple product categories with unlimited nesting
- Product variants and pricing options
- Product screenshots and descriptions
- Featured products and special offers
- Inventory tracking and download limits

### Shopping Experience
- Advanced search with filters and sorting
- Wishlist functionality (planned)
- Product reviews and ratings (planned)
- Related product recommendations
- Shopping cart with coupon support

### Order Processing
- Secure checkout with multiple payment options
- Order tracking and status updates
- Automated email notifications
- Digital product delivery with download links
- Invoice generation and tax calculation

## 🔄 API Endpoints

### Cart Operations
- `POST /api/add_to_cart.php` - Add product to cart
- `POST /api/update_cart.php` - Update cart quantity
- `POST /api/remove_from_cart.php` - Remove from cart

### Payment Processing
- `POST /api/create_stripe_session.php` - Create Stripe payment session
- `POST /api/process_payment.php` - Process payment

### Order Management
- `GET /api/get_order_details.php` - Get order details
- `GET /api/get_downloads.php` - Get download links

## 📧 Email Notifications

The system sends automated emails for:
- User registration confirmation
- Order confirmation and receipt
- Password reset requests
- Download link delivery
- Support ticket responses

## 🚀 Performance Optimization

- Database indexing for fast queries
- Lazy loading for images
- Minified CSS and JavaScript
- Caching headers for static assets
- Optimized database queries
- Pagination for large datasets

## 🌐 Multi-language Support

The system is designed to support multiple languages:
- Language files for easy translation
- RTL language support
- Currency and date format localization
- Multi-language admin interface (planned)

## 🔧 Customization

### Theme Customization
- Easy CSS customization via `assets/css/style.css`
- Color scheme variables in CSS
- Logo and branding updates
- Custom fonts and typography

### Feature Extensions
- Plugin architecture for custom features
- Hook system for extending functionality
- API for third-party integrations
- Custom payment gateway support

## 📞 Support

For support and questions:
- Email: support@s3digital.com
- Documentation: Check inline code comments
- Community: GitHub Issues (if open-sourced)

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🤝 Contributing

Contributions are welcome! Please follow these steps:
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📋 Roadmap

### Upcoming Features
- [ ] Product reviews and ratings system
- [ ] Wishlist functionality
- [ ] Multi-vendor support
- [ ] Subscription-based products
- [ ] Affiliate marketing system
- [ ] Advanced analytics dashboard
- [ ] Mobile app (React Native)
- [ ] API documentation
- [ ] Multi-language support
- [ ] Advanced SEO features

### Technical Improvements
- [ ] Redis caching for performance
- [ ] Queue system for email processing
- [ ] Microservices architecture
- [ ] Docker containerization
- [ ] CI/CD pipeline setup

---

**S3 Digital** - Your Complete Digital Product Marketplace Solution 🚀
