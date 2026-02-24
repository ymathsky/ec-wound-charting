# Setting up Google Vertex AI for EC Wound

To enable advanced AI capabilities using Google Vertex AI, follow these steps:

## 1. Google Cloud Setup
1.  Go to the [Google Cloud Console](https://console.cloud.google.com/).
2.  **Create a Project** (or select an existing one). Note the **Project ID**.
3.  **Enable APIs**:
    *   Search for "Vertex AI API" and enable it.
4.  **Create Service Account**:
    *   Go to **IAM & Admin** > **Service Accounts**.
    *   Click **Create Service Account**.
    *   Name: `ec-wound-ai`.
    *   **Grant Access**: Select the role **Vertex AI User**.
    *   Click **Done**.
5.  **Download Key**:
    *   Click on the newly created service account (email address).
    *   Go to the **Keys** tab.
    *   Click **Add Key** > **Create new key**.
    *   Select **JSON**.
    *   The file will download automatically.

## 2. Install Credentials
1.  Rename the downloaded JSON file to `service_account.json`.
2.  Move this file to the root of your project folder: `c:\xampp\htdocs\ec\service_account.json`.

## 3. Configure Code
1.  Open `api/google_cloud_config.php`.
2.  Update the `GC_PROJECT_ID` with your actual Project ID.
3.  (Optional) Update `GC_LOCATION` if you are not using `us-central1`.

## 4. Test Connection
1.  Open your browser and go to: `http://localhost/ec/api/test_vertex_connection.php`
2.  If successful, you will see a response from the AI.

## 5. Enable in App
Once tested, the application can be switched to use Vertex AI by updating the logic in `api/ai_companion.php` to use the `GoogleAuth` class.
