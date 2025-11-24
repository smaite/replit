# SASTO Hub - Quick Reference Guide

## 🎯 What Changed (Summary)

| Feature | Status | Details |
|---------|--------|---------|
| Settings Database | ✅ | Now saved in MySQL instead of JSON |
| Footer Dynamic | ✅ | All footer info loads from settings |
| Privacy Policy | ✅ | New professional page at `/pages/privacy-policy.php` |
| Terms of Use | ✅ | New professional page at `/pages/terms-of-use.php` |
| Vendor Registration | ✅ | Requires Legal terms acceptance |
| Vendor Folder | ✅ | Migrated from `/vendor/` to `/seller/` |
| Home Page | ✅ | Completely redesigned with huge hero banner |
| Products Page | ✅ | Already had good responsive design |

---

## 🔐 Database Setup (MUST DO FIRST)

Copy and run in MySQL/phpMyAdmin:

```sql
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(255) UNIQUE NOT NULL,
    setting_value LONGTEXT,
    setting_type ENUM('text', 'color', 'url', 'textarea', 'image') DEFAULT 'text',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO settings (setting_key, setting_value, setting_type) VALUES
('website_name', 'SASTO Hub', 'text'),
('website_tagline', 'Your Online Marketplace', 'text'),
('header_logo', '/assets/images/logo.png', 'image'),
('footer_logo', '/assets/images/logo.png', 'image'),
('footer_name', 'SASTO Hub', 'text'),
('copyright_text', '© 2025 SASTO Hub. All rights reserved.', 'text'),
('primary_color', '#4f46e5', 'color'),
('contact_email', 'info@sastohub.com', 'text'),
('contact_phone', '+977 1234567890', 'text'),
('address', 'Kathmandu, Nepal', 'textarea'),
('facebook_url', '', 'url'),
('twitter_url', '', 'url'),
('instagram_url', '', 'url'),
('youtube_url', '', 'url')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);
```

---

## 📍 New/Updated URLs

| URL | Purpose |
|-----|---------|
| `/` | Home (Redesigned with huge hero) |
| `/pages/privacy-policy.php` | Privacy Policy (NEW) |
| `/pages/terms-of-use.php` | Terms of Use (NEW) |
| `/seller/` | Seller Dashboard (was `/vendor/`) |
| `/seller/add-product.php` | Add Product (was `/vendor/add-product.php`) |
| `/admin/settings.php` | Admin Settings (now database-driven) |

---

## 🎨 Home Page Sections (Top to Bottom)

1. **Giant Hero Banner** (600px tall on desktop)
   - Main image with text overlay
   - Two CTA buttons
   - Responsive height

2. **Secondary Banners** (3 smaller ones)
   - Responsive grid
   - Hover effects

3. **Category Section**
   - 12 categories with emoji icons
   - Responsive grid
   - Quick access

4. **Flash Sale Section** (if products with sale_price exist)
   - RED themed
   - "UP TO 70% OFF"
   - 6 products visible

5. **Featured Products Section**
   - 12 featured products
   - ⭐ Badge
   - Responsive grid

6. **New Arrivals Section**
   - 12 newest products
   - NEW badge (green)
   - Latest arrivals

---

## 📱 Responsive Behavior

### Mobile (< 480px)
- 2-column product grid
- Single banner
- Stacked navigation
- Touch-friendly (44px buttons)

### Tablet (480px - 768px)
- 3-column product grid
- 2-3 column categories
- Compact header

### Desktop (> 768px)
- 4-6 column product grid
- 12 categories visible
- Full hero display
- Sidebar filters

---

## 🔗 Important Files

### New Files:
```
DATABASE_SETTINGS_ALTER.sql - Run this SQL
COMPLETE_UPDATE_SUMMARY.md  - Full documentation
pages/privacy-policy.php     - Privacy Policy page
pages/terms-of-use.php       - Terms of Use page
seller/index.php             - Seller dashboard
seller/add-product.php       - Add product page
```

### Modified Files:
```
config/config.php            - Settings functions
admin/settings.php           - Database integration
includes/footer.php          - Dynamic content
includes/header.php          - Link updates
auth/become-vendor.php       - Legal checkboxes
index.php                    - Home page redesign
```

---

## ⚡ How It Works Now

### Settings System:
1. Admin updates settings at `/admin/settings.php`
2. Settings save to MySQL `settings` table
3. Any page can access via `getSetting('key')`
4. Footer automatically updates
5. No JSON files needed

### Vendor Registration:
1. User fills out vendor form
2. User checks 3 legal checkboxes
3. Form validates checkboxes
4. Submission succeeds only if all checked
5. Links open Terms/Privacy in new tab

### Vendor Dashboard:
1. Old: `/vendor/` directory
2. New: `/seller/` directory
3. All functionality identical
4. Composer `/vendor/` folder unchanged

---

## 🧪 Testing Checklist

Before going live:

- [ ] Run SQL script in database
- [ ] Navigate to `/admin/settings.php`
- [ ] Update website name
- [ ] Go to home page - verify it displays correctly
- [ ] Check footer - should show new settings
- [ ] Visit `/pages/privacy-policy.php`
- [ ] Visit `/pages/terms-of-use.php`
- [ ] Try vendor registration - verify checkboxes required
- [ ] Go to `/seller/` - verify dashboard works
- [ ] Test on mobile device
- [ ] Check console for errors (F12)
- [ ] Verify no 404 errors

---

## 🚀 Deployment Notes

### Before Uploading:
1. Backup database
2. Run SQL script
3. Test locally

### Upload These:
- All files in `/seller/` folder (NEW)
- `/pages/privacy-policy.php` (NEW)
- `/pages/terms-of-use.php` (NEW)
- Updated `/config/config.php`
- Updated `/admin/settings.php`
- Updated `/includes/footer.php`
- Updated `/includes/header.php`
- Updated `/auth/become-vendor.php`
- Updated `/index.php`

### Don't Upload:
- `/vendor/` folder (composer libraries)
- Remove old `/vendor/` php files if you want

---

## 💡 Pro Tips

### To Change Colors:
1. Go to Admin Settings
2. Change "Primary Color" field
3. All buttons will update (if using CSS variables)

### To Change Footer Info:
1. Admin Settings
2. Update contact_email, contact_phone, address
3. Footer updates automatically

### To Add Social Links:
1. Admin Settings
2. Add Facebook/Twitter/Instagram/YouTube URLs
3. Social icons appear in footer

### To Add Banners:
1. Admin > Banners section
2. First banner becomes hero image
3. Next 3 become secondary banners

---

## ❓ FAQ

**Q: What about my old /vendor/ files?**
A: Copy important files to /seller/. The /vendor/ folder is for Composer libraries.

**Q: Will settings work without database?**
A: No, settings MUST be in database now. Run the SQL script.

**Q: Can I use old JSON settings?**
A: No, the code now uses database only. JSON file is ignored.

**Q: How do vendors access their dashboard?**
A: Old: `/vendor/` | New: `/seller/` (same functionality)

**Q: Are checkboxes on vendor registration required?**
A: Yes, all 3 must be checked to submit form.

**Q: Does old vendor folder still work?**
A: No, all functionality moved to /seller/

**Q: What if database settings table doesn't exist?**
A: Homepage will use default values. Run SQL to fix it.

---

## 🎯 What Users See

### First-Time Visitor:
- Impressive giant hero banner
- Clear categories to browse
- Flash sale section if deals exist
- Featured products highlighted
- Professional privacy/terms policies

### Vendor:
- Must accept terms to register
- Dashboard at new `/seller/` URL
- Same functionality as before
- Professional legal framework

### Admin:
- Simple settings management
- No JSON files to maintain
- Settings persist in database
- Can customize entire site appearance

---

## 🔄 Responsive Grid Reference

```
Mobile (<480px):   2 columns
Mobile (480-768px): 3 columns  
Desktop (>768px):   4-6 columns (varies by section)
```

---

**Version:** 1.0 Complete
**Date:** November 2025
**Status:** Ready for Production
