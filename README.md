# **SnackAura**

SnackAura is a PHP-based web application for managing snack or food ordering, designed for small-scale restaurants or snack shops. It allows users to browse products, customize orders, manage carts, and finalize purchases with ease. The system also supports administrative and dynamic configurations for store settings.

---

## **Features**

- **Product Management**
  - View categories, drinks, extras, and product details dynamically.
- **Shopping Cart**
  - Add, edit, and remove items from the cart.
- **Order Processing**
  - Checkout functionality with real-time price calculations.
- **Rating and Feedback**
  - Submit ratings for products and services.
- **Reservation System**
  - Allow users to make table reservations.
- **Store Configuration**
  - Dynamic settings for opening hours and promotional banners.
- **Modular UI Components**
  - Modals for legal agreements, store details, and user feedback.
- **API Integration**
  - Extend functionality using APIs for various data operations.

---

## **Project Structure**

```
SnackAura/
├── admin/               # Administrative tools and features
├── api/                 # API endpoints for dynamic functionality
├── assets/              # Static files (CSS, JS, Images)
├── includes/            # Reusable components
├── uploads/             # Directory for uploaded media
├── index.php            # Main entry point of the application
├── style.css            # Application styles
├── script.php           # Frontend interactions and scripting
├── db.php               # Database connection
├── functions.php        # Reusable PHP functions
├── composer.json        # PHP dependency manager configuration
└── .env                 # Configuration file (should not be public)
```

---

## **Setup Instructions**

### **Prerequisites**

1. **Server Requirements**:
   - PHP 7.4 or higher
   - MySQL database
   - Composer (for dependency management)
   - Apache or Nginx web server

2. **Environment Variables**:
   Create a `.env` file in the root directory with the following structure:
   ```
   DB_HOST=your_database_host
   DB_NAME=your_database_name
   DB_USER=your_database_user
   DB_PASSWORD=your_database_password
   ```

3. **Install Dependencies**:
   Run the following command to install PHP dependencies:
   ```bash
   composer install
   ```

4. **Database Setup**:
   - Import the `snackaura.sql` (or similar) file into your MySQL database.

5. **Permissions**:
   Ensure the `/uploads` directory has write permissions:
   ```bash
   chmod -R 755 uploads/
   ```

---

## **How to Run**

1. Start your local server (e.g., XAMPP, WAMP, or MAMP).
2. Place the project folder in your server's root directory.
3. Access the application via your browser:
   ```
   http://localhost/SnackAura/
   ```

---

## **Key Functionalities**

### **1. Product Management**
- Fetch products, categories, drinks, and extras dynamically via:
  - `get_products.php`
  - `get_categories.php`
  - `get_drinks.php`
  - `get_extras.php`

### **2. Shopping Cart**
- Add or edit cart items using:
  - `cart_modal.php`
  - `edit_cart.php`

### **3. Order Placement**
- Handle checkout and order submission:
  - `checkout.php`
  - `place_order.php`

### **4. Ratings and Reservations**
- Submit user ratings: `submit_rating.php`
- Make reservations: `submit_reservation.php`

### **5. Store Management**
- Check store status dynamically: `check_store_status.php`
- Display promotional banners: `promotional_banners.php`

---

## **Security Guidelines**

1. **Environment File Protection**:
   - Add `.env` to `.gitignore` to avoid exposing sensitive credentials.
2. **Input Validation**:
   - Use prepared statements for database queries to prevent SQL injection.
3. **Session Security**:
   - Use secure sessions for user authentication and authorization.
4. **CSRF Protection**:
   - Add CSRF tokens to all form submissions.

---

## **Planned Improvements**

1. **Framework Migration**:
   - Transition to Laravel or Symfony for scalability.
2. **API Security**:
   - Implement JWT or OAuth2 for API authentication.
3. **Caching**:
   - Add caching mechanisms for frequently fetched data using Redis or Memcached.
4. **Testing**:
   - Write unit and integration tests using PHPUnit.
5. **Localization**:
   - Support multiple languages for a broader audience.

---

## **Contributing**

We welcome contributions to improve SnackAura! Please follow these steps:

1. Fork the repository.
2. Create a new branch:
   ```bash
   git checkout -b feature/your-feature-name
   ```
3. Commit your changes:
   ```bash
   git commit -m "Add your message here"
   ```
4. Push the branch:
   ```bash
   git push origin feature/your-feature-name
   ```
5. Submit a pull request.

---

## **License**

This project is licensed under the [MIT License](LICENSE).

---

## **Contact**

For questions or support, contact **Enis Gjinii** via [GitHub Issues](https://github.com/enisgjinii/SnackAura/issues).

---

Feel free to customize this as needed for your specific project goals!
