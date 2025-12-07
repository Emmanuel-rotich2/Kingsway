<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Configuration - Kingsway Academy</title>
    <link rel="stylesheet" href="../king.css">

    <style>
        body {
            background: #f4f6f9;
            font-family: Arial, Helvetica, sans-serif;
        }

        .config-container {
            max-width: 1180px;
            margin: 25px auto;
            padding: 10px;
        }

        h1 {
            color: #2c3e50;
            text-align: left;
            margin-bottom: 25px;
            font-size: 26px;
        }

        .config-section {
            background: #ffffff;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 3px 6px rgba(0,0,0,0.08);
        }

        .config-section h2 {
            border-left: 4px solid #3498db;
            padding-left: 12px;
            margin-bottom: 25px;
            color: #2c3e50;
            font-size: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 7px;
            color: #34495e;
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 10px 12px;
            border-radius: 6px;
            border: 1px solid #dcdde1;
            background: #fff;
            font-size: 15px;
        }

        textarea {
            min-height: 110px;
        }

        .image-upload-section {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .image-preview {
            width: 120px;
            height: 120px;
            border-radius: 10px;
            border: 2px dashed #bdc3c7;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #f4f6f9;
        }

        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .btn {
            padding: 10px 18px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            border: none;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }
        .btn-success:hover {
            background: #1e8449;
        }

        .btn-secondary {
            background: #7f8c8d;
            color: white;
        }

        .btn-secondary:hover {
            background: #707b7c;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            animation: fadeIn 0.4s ease-out;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 5px solid #1e8449;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 5px solid #c0392b;
        }

        .loading {
            display: none;
            padding: 20px;
            text-align: center;
        }

        .spinner {
            width: 45px;
            height: 45px;
            border: 5px solid #ddd;
            border-top: 5px solid #3498db;
            border-radius: 50%;
            margin: 0 auto;
            animation: spin 1s linear infinite;
        }

        @keyframes spin { 0%{transform:rotate(0deg);} 100%{transform:rotate(360deg);} }
    </style>
</head>

<body>

    <?php
    session_start();
    require_once '../config/config.php';
    require_once '../api/includes/auth_middleware.php';

    // Only admins allowed
    requireAuth(['admin', 'super_admin']);

    include '../layouts/app_layout.php';
    ?>

    <div class="config-container">
        <h1>School Configuration</h1>

        <div id="alertContainer"></div>

        <!-- LOADING INDICATOR -->
        <div class="loading" id="loadingIndicator">
            <div class="spinner"></div>
            <p>Loading...</p>
        </div>

        <form id="configForm">

            <!-- BASIC INFORMATION -->
            <div class="config-section">
                <h2>Basic Information</h2>

                <div class="form-grid">

                    <div class="form-group">
                        <label>School Name *</label>
                        <input type="text" id="school_name" name="school_name" required>
                    </div>

                    <div class="form-group">
                        <label>School Code</label>
                        <input type="text" id="school_code" name="school_code">
                    </div>

                    <div class="form-group">
                        <label>Established Year</label>
                        <input type="number" id="established_year" name="established_year" min="1900" max="2100">
                    </div>
                </div>

                <div class="form-group">
                    <label>School Motto</label>
                    <input type="text" id="motto" name="motto">
                </div>

                <div class="form-group">
                    <label>Vision Statement</label>
                    <textarea id="vision" name="vision"></textarea>
                </div>

                <div class="form-group">
                    <label>Mission Statement</label>
                    <textarea id="mission" name="mission"></textarea>
                </div>

                <div class="form-group">
                    <label>Core Values</label>
                    <textarea id="core_values" name="core_values" placeholder="Enter values separated by commas"></textarea>
                </div>

                <div class="form-group">
                    <label>About Us</label>
                    <textarea id="about_us" name="about_us"></textarea>
                </div>
            </div>

            <!-- LOGO BRANDING -->
            <div class="config-section">
                <h2>Logo & Branding</h2>

                <div class="form-grid">

                    <div class="form-group">
                        <label>School Logo</label>

                        <div class="image-upload-section">
                            <div class="image-preview" id="logoPreview"></div>

                            <div>
                                <input type="file" id="logoFile" accept="image/*" hidden>
                                <button class="btn btn-primary" type="button" onclick="document.getElementById('logoFile').click()">Upload Logo</button>

                                <p style="font-size:12px;color:#7f8c8d;">500x500px recommended</p>
                            </div>
                        </div>

                        <input type="hidden" id="logo_url" name="logo_url">
                    </div>

                    <div class="form-group">
                        <label>Favicon</label>

                        <div class="image-upload-section">
                            <div class="image-preview" id="faviconPreview"></div>

                            <div>
                                <input type="file" id="faviconFile" accept="image/*" hidden>
                                <button class="btn btn-primary" type="button" onclick="document.getElementById('faviconFile').click()">Upload Favicon</button>

                                <p style="font-size:12px;color:#7f8c8d;">32x32px recommended</p>
                            </div>
                        </div>

                        <input type="hidden" id="favicon_url" name="favicon_url">
                    </div>
                </div>
            </div>

            <!-- CONTACT INFORMATION -->
            <div class="config-section">
                <h2>Contact Information</h2>

                <div class="form-grid">

                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" id="email" name="email">
                    </div>

                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" id="phone" name="phone">
                    </div>

                    <div class="form-group">
                        <label>Alternative Phone</label>
                        <input type="tel" id="alternative_phone" name="alternative_phone">
                    </div>

                    <div class="form-group">
                        <label>Website</label>
                        <input type="url" id="website" name="website">
                    </div>
                </div>

                <div class="form-group">
                    <label>Physical Address</label>
                    <textarea id="address" name="address"></textarea>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" id="city" name="city">
                    </div>

                    <div class="form-group">
                        <label>County/State</label>
                        <input type="text" id="state" name="state">
                    </div>

                    <div class="form-group">
                        <label>Country</label>
                        <input type="text" id="country" name="country">
                    </div>

                    <div class="form-group">
                        <label>Postal Code</label>
                        <input type="text" id="postal_code" name="postal_code">
                    </div>
                </div>
            </div>

            <!-- SOCIAL MEDIA -->
            <div class="config-section">
                <h2>Social Media Links</h2>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Facebook</label>
                        <input type="url" id="facebook_url" name="facebook_url">
                    </div>

                    <div class="form-group">
                        <label>Twitter/X</label>
                        <input type="url" id="twitter_url" name="twitter_url">
                    </div>

                    <div class="form-group">
                        <label>Instagram</label>
                        <input type="url" id="instagram_url" name="instagram_url">
                    </div>

                    <div class="form-group">
                        <label>LinkedIn</label>
                        <input type="url" id="linkedin_url" name="linkedin_url">
                    </div>

                    <div class="form-group">
                        <label>YouTube</label>
                        <input type="url" id="youtube_url" name="youtube_url">
                    </div>
                </div>
            </div>

            <!-- PRINCIPAL INFO -->
            <div class="config-section">
                <h2>Principal Information</h2>

                <div class="form-group">
                    <label>Principal Name</label>
                    <input type="text" id="principal_name" name="principal_name">
                </div>

                <div class="form-group">
                    <label>Principal Message</label>
                    <textarea id="principal_message" name="principal_message"></textarea>
                </div>
            </div>

            <!-- DOCUMENTS -->
            <div class="config-section">
                <h2>Documents & Resources</h2>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Academic Calendar URL</label>
                        <input type="url" id="academic_calendar_url" name="academic_calendar_url">
                    </div>

                    <div class="form-group">
                        <label>Prospectus URL</label>
                        <input type="url" id="prospectus_url" name="prospectus_url">
                    </div>

                    <div class="form-group">
                        <label>Student Handbook URL</label>
                        <input type="url" id="student_handbook_url" name="student_handbook_url">
                    </div>
                </div>
            </div>

            <!-- SYSTEM SETTINGS -->
            <div class="config-section">
                <h2>System Settings</h2>

                <div class="form-grid">

                    <div class="form-group">
                        <label>Timezone</label>
                        <select id="timezone" name="timezone">
                            <option value="Africa/Nairobi">Africa/Nairobi (EAT)</option>
                            <option value="UTC">UTC</option>
                            <option value="Africa/Lagos">Africa/Lagos</option>
                            <option value="Africa/Johannesburg">Africa/Johannesburg</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Currency</label>
                        <select id="currency" name="currency">
                            <option value="KES">KES – Kenyan Shilling</option>
                            <option value="USD">USD – US Dollar</option>
                            <option value="EUR">Euro</option>
                            <option value="GBP">GBP – British Pound</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Language</label>
                        <select id="language" name="language">
                            <option value="en">English</option>
                            <option value="sw">Swahili</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ACTION BUTTONS -->
            <div class="form-group" style="text-align:right; margin-top:25px;">
                <button type="button" class="btn btn-secondary" onclick="loadConfiguration()">Reset</button>
                <button type="submit" class="btn btn-success">Save Configuration</button>
            </div>

        </form>
    </div>

    <script>
        // LOAD ON PAGE START
        document.addEventListener('DOMContentLoaded', () => {
            loadConfiguration();
            bindUploadHandlers();
        });

        // PRELOADING
        function showLoading(show) {
            document.getElementById('loadingIndicator').style.display = show ? 'block' : 'none';
        }

        // ALERT MESSAGE
        function showAlert(message, type = 'success') {
            const container = document.getElementById('alertContainer');
            const className = type === 'success' ? 'alert-success' : 'alert-error';

            container.innerHTML = `
                <div class="alert ${className}">
                    ${message}
                </div>
            `;

            setTimeout(() => container.innerHTML = '', 4500);
        }

        // FILL FORM
        function populateForm(data) {
            Object.entries(data).forEach(([key, value]) => {
                const el = document.getElementById(key);
                if (el) el.value = value ?? '';
            });

            if (data.logo_url) updateImagePreview('logoPreview', data.logo_url);
            if (data.favicon_url) updateImagePreview('faviconPreview', data.favicon_url);
        }

        // LOAD EXISTING CONFIG
        async function loadConfiguration() {
            showLoading(true);

            try {
                const res = await fetch('/api/school_config.php?action=get');
                const json = await res.json();

                if (json.success) {
                    populateForm(json.data);
                    showAlert('Configuration loaded successfully', 'success');
                } else {
                    showAlert(json.message, 'error');
                }
            } catch (err) {
                showAlert('Error loading configuration', 'error');
            }

            showLoading(false);
        }

        // UPLOAD FILE
        async function uploadFile(file, type, hiddenField, previewDiv) {
            const formData = new FormData();
            formData.append('file', file);

            showLoading(true);

            try {
                const action = type === 'logo' ? 'upload_logo' : 'upload_favicon';

                const res = await fetch(`/api/school_config.php?action=${action}`, {
                    method: 'POST',
                    body: formData
                });

                const json = await res.json();

                if (json.success) {
                    document.getElementById(hiddenField).value = json.url;
                    updateImagePreview(previewDiv, json.url);
                    showAlert(json.message, 'success');
                } else {
                    showAlert(json.message, 'error');
                }
            } catch (err) {
                showAlert('Upload error: ' + err.message, 'error');
            }

            showLoading(false);
        }

        // SET PREVIEW
        function updateImagePreview(divId, url) {
            const div = document.getElementById(divId);
            div.innerHTML = `<img src="${url}">`;
        }

        // SETUP FILE BUTTONS
        function bindUploadHandlers() {
            document.getElementById('logoFile').addEventListener('change', e => {
                uploadFile(e.target.files[0], 'logo', 'logo_url', 'logoPreview');
            });

            document.getElementById('faviconFile').addEventListener('change', e => {
                uploadFile(e.target.files[0], 'favicon', 'favicon_url', 'faviconPreview');
            });
        }

        // SAVE CONFIG
        document.getElementById('configForm').addEventListener('submit', async e => {
            e.preventDefault();

            showLoading(true);

            const data = Object.fromEntries(new FormData(e.target));

            try {
                const res = await fetch('/api/school_config.php?action=update', {
                    method: 'POST',
                    body: JSON.stringify(data),
                    headers: { 'Content-Type': 'application/json' }
                });

                const json = await res.json();

                if (json.success) {
                    showAlert('Configuration saved successfully');
                } else {
                    showAlert(json.message, 'error');
                }
            } catch (err) {
                showAlert('Save failed: ' + err.message, 'error');
            }

            showLoading(false);
        });
    </script>

</body>
</html>
