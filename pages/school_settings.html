<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Configuration - Kingsway Academy</title>
    <link rel="stylesheet" href="../king.css">
    <style>
        .config-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        .config-section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .config-section h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #34495e;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
        }

        .image-upload-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .image-preview {
            width: 120px;
            height: 120px;
            border: 2px dashed #ddd;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .btn-success {
            background-color: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background-color: #229954;
        }

        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #7f8c8d;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <?php
    session_start();
    require_once '../config/config.php';
    require_once '../api/includes/auth_middleware.php';
    
    // Check authentication and authorization
    requireAuth(['admin', 'super_admin']);
    
    include '../layouts/app_layout.php';
    ?>

    <div class="config-container">
        <h1>School Configuration</h1>
        
        <div id="alertContainer"></div>
        
        <div class="loading" id="loadingIndicator">
            <div class="spinner"></div>
            <p>Loading...</p>
        </div>

        <form id="configForm">
            <!-- Basic Information -->
            <div class="config-section">
                <h2>Basic Information</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="school_name">School Name *</label>
                        <input type="text" id="school_name" name="school_name" required>
                    </div>
                    <div class="form-group">
                        <label for="school_code">School Code</label>
                        <input type="text" id="school_code" name="school_code">
                    </div>
                    <div class="form-group">
                        <label for="established_year">Established Year</label>
                        <input type="number" id="established_year" name="established_year" min="1900" max="2100">
                    </div>
                </div>

                <div class="form-group">
                    <label for="motto">School Motto</label>
                    <input type="text" id="motto" name="motto">
                </div>

                <div class="form-group">
                    <label for="vision">Vision Statement</label>
                    <textarea id="vision" name="vision"></textarea>
                </div>

                <div class="form-group">
                    <label for="mission">Mission Statement</label>
                    <textarea id="mission" name="mission"></textarea>
                </div>

                <div class="form-group">
                    <label for="core_values">Core Values</label>
                    <textarea id="core_values" name="core_values" placeholder="Enter core values separated by commas"></textarea>
                </div>

                <div class="form-group">
                    <label for="about_us">About Us</label>
                    <textarea id="about_us" name="about_us"></textarea>
                </div>
            </div>

            <!-- Logo and Branding -->
            <div class="config-section">
                <h2>Logo & Branding</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label>School Logo</label>
                        <div class="image-upload-section">
                            <div class="image-preview" id="logoPreview">
                                <span>No logo</span>
                            </div>
                            <div>
                                <input type="file" id="logoFile" accept="image/*" style="display: none;">
                                <button type="button" class="btn btn-primary" onclick="document.getElementById('logoFile').click()">
                                    Upload Logo
                                </button>
                                <p style="font-size: 12px; color: #7f8c8d; margin-top: 5px;">
                                    Recommended: 500x500px, Max 5MB
                                </p>
                            </div>
                        </div>
                        <input type="hidden" id="logo_url" name="logo_url">
                    </div>

                    <div class="form-group">
                        <label>Favicon</label>
                        <div class="image-upload-section">
                            <div class="image-preview" id="faviconPreview">
                                <span>No favicon</span>
                            </div>
                            <div>
                                <input type="file" id="faviconFile" accept="image/*" style="display: none;">
                                <button type="button" class="btn btn-primary" onclick="document.getElementById('faviconFile').click()">
                                    Upload Favicon
                                </button>
                                <p style="font-size: 12px; color: #7f8c8d; margin-top: 5px;">
                                    Recommended: 32x32px or 64x64px
                                </p>
                            </div>
                        </div>
                        <input type="hidden" id="favicon_url" name="favicon_url">
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="config-section">
                <h2>Contact Information</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone">
                    </div>
                    <div class="form-group">
                        <label for="alternative_phone">Alternative Phone</label>
                        <input type="tel" id="alternative_phone" name="alternative_phone">
                    </div>
                    <div class="form-group">
                        <label for="website">Website URL</label>
                        <input type="url" id="website" name="website">
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">Physical Address</label>
                    <textarea id="address" name="address"></textarea>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="city">City</label>
                        <input type="text" id="city" name="city">
                    </div>
                    <div class="form-group">
                        <label for="state">State/County</label>
                        <input type="text" id="state" name="state">
                    </div>
                    <div class="form-group">
                        <label for="country">Country</label>
                        <input type="text" id="country" name="country">
                    </div>
                    <div class="form-group">
                        <label for="postal_code">Postal Code</label>
                        <input type="text" id="postal_code" name="postal_code">
                    </div>
                </div>
            </div>

            <!-- Social Media -->
            <div class="config-section">
                <h2>Social Media Links</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="facebook_url">Facebook URL</label>
                        <input type="url" id="facebook_url" name="facebook_url" placeholder="https://facebook.com/...">
                    </div>
                    <div class="form-group">
                        <label for="twitter_url">Twitter/X URL</label>
                        <input type="url" id="twitter_url" name="twitter_url" placeholder="https://twitter.com/...">
                    </div>
                    <div class="form-group">
                        <label for="instagram_url">Instagram URL</label>
                        <input type="url" id="instagram_url" name="instagram_url" placeholder="https://instagram.com/...">
                    </div>
                    <div class="form-group">
                        <label for="linkedin_url">LinkedIn URL</label>
                        <input type="url" id="linkedin_url" name="linkedin_url" placeholder="https://linkedin.com/...">
                    </div>
                    <div class="form-group">
                        <label for="youtube_url">YouTube URL</label>
                        <input type="url" id="youtube_url" name="youtube_url" placeholder="https://youtube.com/...">
                    </div>
                </div>
            </div>

            <!-- Principal Information -->
            <div class="config-section">
                <h2>Principal Information</h2>
                <div class="form-group">
                    <label for="principal_name">Principal's Name</label>
                    <input type="text" id="principal_name" name="principal_name">
                </div>
                <div class="form-group">
                    <label for="principal_message">Principal's Message</label>
                    <textarea id="principal_message" name="principal_message"></textarea>
                </div>
            </div>

            <!-- Documents -->
            <div class="config-section">
                <h2>Documents & Resources</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="academic_calendar_url">Academic Calendar URL</label>
                        <input type="url" id="academic_calendar_url" name="academic_calendar_url">
                    </div>
                    <div class="form-group">
                        <label for="prospectus_url">Prospectus URL</label>
                        <input type="url" id="prospectus_url" name="prospectus_url">
                    </div>
                    <div class="form-group">
                        <label for="student_handbook_url">Student Handbook URL</label>
                        <input type="url" id="student_handbook_url" name="student_handbook_url">
                    </div>
                </div>
            </div>

            <!-- System Settings -->
            <div class="config-section">
                <h2>System Settings</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="timezone">Timezone</label>
                        <select id="timezone" name="timezone">
                            <option value="Africa/Nairobi">Africa/Nairobi (EAT)</option>
                            <option value="Africa/Lagos">Africa/Lagos (WAT)</option>
                            <option value="Africa/Johannesburg">Africa/Johannesburg (SAST)</option>
                            <option value="UTC">UTC</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="currency">Currency</label>
                        <select id="currency" name="currency">
                            <option value="KES">KES - Kenyan Shilling</option>
                            <option value="USD">USD - US Dollar</option>
                            <option value="EUR">EUR - Euro</option>
                            <option value="GBP">GBP - British Pound</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="language">Language</label>
                        <select id="language" name="language">
                            <option value="en">English</option>
                            <option value="sw">Swahili</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="action-buttons">
                <button type="button" class="btn btn-secondary" onclick="loadConfiguration()">Reset</button>
                <button type="submit" class="btn btn-success">Save Configuration</button>
            </div>
        </form>
    </div>

    <script>
        // Load configuration on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadConfiguration();
            setupFileUploads();
        });

        // Load current configuration
        async function loadConfiguration() {
            showLoading(true);
            try {
                const response = await fetch('/api/school_config.php?action=get');
                const result = await response.json();

                if (result.success) {
                    populateForm(result.data);
                    showAlert('Configuration loaded successfully', 'success');
                } else {
                    showAlert('Failed to load configuration: ' + result.message, 'error');
                }
            } catch (error) {
                showAlert('Error loading configuration: ' + error.message, 'error');
            } finally {
                showLoading(false);
            }
        }

        // Populate form with data
        function populateForm(data) {
            for (const [key, value] of Object.entries(data)) {
                const field = document.getElementById(key);
                if (field) {
                    field.value = value || '';
                }
            }

            // Update image previews
            if (data.logo_url) {
                updateImagePreview('logoPreview', data.logo_url);
            }
            if (data.favicon_url) {
                updateImagePreview('faviconPreview', data.favicon_url);
            }
        }

        // Handle form submission
        document.getElementById('configForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            showLoading(true);
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            try {
                const response = await fetch('/api/school_config.php?action=update', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Configuration updated successfully!', 'success');
                } else {
                    showAlert('Failed to update configuration: ' + result.message, 'error');
                }
            } catch (error) {
                showAlert('Error updating configuration: ' + error.message, 'error');
            } finally {
                showLoading(false);
            }
        });

        // Setup file upload handlers
        function setupFileUploads() {
            document.getElementById('logoFile').addEventListener('change', function(e) {
                uploadFile(e.target.files[0], 'logo', 'logo_url', 'logoPreview');
            });

            document.getElementById('faviconFile').addEventListener('change', function(e) {
                uploadFile(e.target.files[0], 'favicon', 'favicon_url', 'faviconPreview');
            });
        }

        // Upload file
        async function uploadFile(file, type, urlFieldId, previewId) {
            if (!file) return;

            showLoading(true);
            const formData = new FormData();
            formData.append('file', file);

            try {
                const action = type === 'logo' ? 'upload_logo' : 'upload_favicon';
                const response = await fetch(`/api/school_config.php?action=${action}`, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    document.getElementById(urlFieldId).value = result.url;
                    updateImagePreview(previewId, result.url);
                    showAlert(result.message, 'success');
                } else {
                    showAlert('Upload failed: ' + result.message, 'error');
                }
            } catch (error) {
                showAlert('Error uploading file: ' + error.message, 'error');
            } finally {
                showLoading(false);
            }
        }

        // Update image preview
        function updateImagePreview(previewId, url) {
            const preview = document.getElementById(previewId);
            preview.innerHTML = `<img src="${url}" alt="Preview">`;
        }

        // Show alert
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            
            alertContainer.innerHTML = `
                <div class="alert ${alertClass}">
                    ${message}
                </div>
            `;

            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }

        // Show/hide loading indicator
        function showLoading(show) {
            document.getElementById('loadingIndicator').style.display = show ? 'block' : 'none';
        }
    </script>
</body>
</html>
