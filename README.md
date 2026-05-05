# UTTMart - Microservices E-commerce Platform

Dự án UTTMart là hệ thống thương mại điện tử được xây dựng theo kiến trúc microservices với PHP và MySQL.

## 🏗️ Kiến trúc

- **Frontend**: HTML, CSS, JavaScript (Vanilla)
- **Backend**: 8 Microservices PHP
- **Database**: MySQL (nhiều database riêng biệt)
- **API Gateway**: Nginx
- **Container**: Docker & Docker Compose

## 📋 Yêu cầu hệ thống

- **Docker**: Version 20.10+
- **Docker Compose**: Version 2.0+
- **RAM**: Tối thiểu 4GB
- **Disk**: Tối thiểu 2GB trống
- **OS**: Windows/Linux/Mac

## 🚀 Cài đặt và chạy

### Bước 1: Clone repository
```bash
git clone <repository-url>
cd pjfinal
```

### Bước 2: Khởi động hệ thống
```bash
# Chạy tất cả services
docker-compose up -d

# Xem logs (tùy chọn)
docker-compose logs -f
```

### Bước 3: Kiểm tra trạng thái
```bash
# Kiểm tra containers đang chạy
docker-compose ps

# Chờ database khởi tạo xong (khoảng 30-60 giây)
```

### Bước 5: Kiểm tra hệ thống (tùy chọn)
```bash
# Linux/Mac
./health-check.sh

# Windows
health-check.bat
```

## 📁 Cấu trúc dự án

```
pjfinal/
├── README.md              # Hướng dẫn này
├── .gitignore            # Git ignore rules
├── .dockerignore         # Docker ignore rules
├── docker-compose.yml    # Docker orchestration
├── health-check.sh       # System health check (Linux/Mac)
├── health-check.bat      # System health check (Windows)
├── databases/            # SQL schema files
├── infra/               # Docker configurations
│   ├── gateway/         # Nginx API gateway
│   └── php-apache/      # PHP Apache base image
├── services/            # Microservices
│   ├── user-service/
│   ├── product-service/
│   ├── order-service/
│   ├── customer-service/
│   ├── payment-service/
│   ├── employee-service/
│   ├── inventory-service/
│   └── attendance-service/
└── web/                 # Frontend static files
    ├── *.html           # Pages
    ├── main.js          # Shared JavaScript
    └── images/          # Static assets
```

## 🔧 Troubleshooting

### Lỗi thường gặp:

#### 1. Port 8080 đã được sử dụng
```bash
# Kiểm tra port
netstat -ano | findstr :8080

# Thay đổi port trong docker-compose.yml
ports:
  - "8081:80"  # Thay 8080 thành 8081
```

#### 2. Database connection failed
```bash
# Kiểm tra database container
docker-compose ps

# Restart database
docker-compose restart order-db
```

#### 3. Permission denied
```bash
# Trên Linux/Mac
sudo chmod -R 755 .

# Trên Windows - chạy Command Prompt as Administrator
```

#### 4. Out of memory
```bash
# Tăng RAM cho Docker Desktop
# Hoặc giảm services chạy đồng thời
docker-compose up -d user-service product-service order-service
```

### Logs debugging:
```bash
# Xem lỗi PHP
docker-compose logs order-service

# Xem lỗi database
docker-compose logs order-db
```

## 📞 Hỗ trợ

Nếu gặp vấn đề, kiểm tra:
1. Docker và Docker Compose đã cài đặt
2. Port 8080 không bị chiếm
3. Đủ RAM (4GB+)
4. Firewall không chặn port

## 🎯 Features

- ✅ Microservices Architecture
- ✅ User Authentication & Authorization
- ✅ Product Catalog & Inventory
- ✅ Shopping Cart & Wishlist
- ✅ Order Management
- ✅ Payment Integration (MoMo)
- ✅ Admin Dashboard
- ✅ Employee Management
- ✅ Attendance Tracking
- ✅ Expense Management
- ✅ Multi-tenant Database Design

---

**Happy coding! 🚀**</content>
<parameter name="filePath">d:\pjfinal\README.md