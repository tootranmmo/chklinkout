# ChkLinkOut - External Link Crawler

WordPress plugin để crawl và liệt kê tất cả external links trong website, hiển thị vị trí và bài viết chứa link.

## Tính năng

- ✅ Quét tất cả posts, pages, custom post types
- ✅ Quét widgets và sidebars
- ✅ Quét custom fields
- ✅ Hiển thị vị trí chính xác của mỗi external link (content, excerpt, custom field, widget)
- ✅ Thống kê tổng quan (số lượng posts, links, unique domains)
- ✅ Lọc theo loại content (post, page, widget)
- ✅ Tìm kiếm theo URL hoặc tiêu đề
- ✅ Xuất kết quả ra file CSV
- ✅ Giao diện admin thân thiện và responsive

## Cài đặt

### Cách 1: Upload qua WordPress Admin

1. Nén thư mục `chklinkout` thành file `chklinkout.zip`
2. Vào WordPress Admin → Plugins → Add New
3. Click "Upload Plugin" và chọn file `chklinkout.zip`
4. Click "Install Now" và sau đó "Activate"

### Cách 2: Upload qua FTP

1. Upload thư mục `chklinkout` vào `/wp-content/plugins/`
2. Vào WordPress Admin → Plugins
3. Tìm "ChkLinkOut - External Link Crawler" và click "Activate"

### Cách 3: Sử dụng WP-CLI

```bash
cd /path/to/wordpress
wp plugin activate chklinkout
```

## Sử dụng

1. Sau khi activate plugin, vào menu **External Links** trong WordPress Admin
2. Click nút **"Bắt đầu quét"** để bắt đầu scan website
3. Kết quả sẽ hiển thị:
   - Thống kê tổng quan
   - Danh sách top domains
   - Bảng chi tiết các external links
4. Sử dụng các bộ lọc để:
   - Lọc theo loại (post, page, widget)
   - Tìm kiếm theo URL hoặc tiêu đề
5. Click **"Xuất CSV"** để export dữ liệu

## Cấu trúc Plugin

```
chklinkout/
├── chklinkout.php                          # Main plugin file
├── includes/
│   ├── class-external-link-crawler.php    # Core crawler class
│   └── class-admin-page.php               # Admin page display
├── admin/
│   ├── css/
│   │   └── admin-style.css                # Admin styles
│   └── js/
│       └── admin-script.js                # Admin JavaScript
└── README.md                               # Documentation
```

## Các vị trí được quét

Plugin sẽ quét external links tại các vị trí sau:

### 1. Posts & Pages
- **Nội dung bài viết** (post_content)
- **Trích dẫn** (post_excerpt)
- **Custom fields** (post meta)

### 2. Custom Post Types
- Tất cả custom post types public được đăng ký trong WordPress

### 3. Widgets
- Text widgets
- Custom HTML widgets
- Các widgets khác có chứa nội dung HTML

## Kết quả hiển thị

Mỗi external link sẽ bao gồm thông tin:

- **URL**: Đường dẫn đầy đủ của external link
- **Vị trí**: Nơi link được tìm thấy (Content, Excerpt, Widget, Custom Field)
- **Bài viết/Trang**: Tiêu đề và loại của content chứa link
- **Thao tác**: Nút Edit và View để truy cập nhanh

## Thống kê

Plugin cung cấp các thống kê sau:

- **Tổng số mục**: Tổng số posts/pages/widgets chứa external links
- **Tổng External Links**: Tổng số external links được tìm thấy
- **Unique Domains**: Số lượng domains duy nhất
- **Top Domains**: 10 domains xuất hiện nhiều nhất

## Export CSV

File CSV xuất ra sẽ chứa các cột:

- Tiêu đề
- Loại (post, page, widget)
- External Link
- Vị trí
- URL Bài viết

## Yêu cầu hệ thống

- WordPress 5.0 trở lên
- PHP 7.0 trở lên
- MySQL 5.6 trở lên

## Hỗ trợ

Nếu gặp vấn đề hoặc có câu hỏi, vui lòng tạo issue tại:
https://github.com/tootranmmo/chklinkout/issues

## License

GPL v2 or later

## Changelog

### 1.0.0 (2025-11-21)
- Initial release
- Quét posts, pages, custom post types
- Quét widgets
- Thống kê và báo cáo
- Export CSV
- Giao diện admin responsive
