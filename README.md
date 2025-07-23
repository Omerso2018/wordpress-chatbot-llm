# wordpress-chatbot-llm
Minimal WordPress chatbot using LLMs via API, ready to paste via Code Snippets.
# 🧠 WordPress AI Chatbot 


<img width="2560" height="1440" alt="New Project (10)" src="https://github.com/user-attachments/assets/0e45e1da-d780-4873-8501-a6224d4f9d24" />


A lightweight, self-contained AI chatbot for WordPress — combining frontend UI and backend logic in one PHP file. Easily connect it to LLMs like OpenRouter or Together AI via API.

---

## ⚙️ Features

- 💬 Chatbot UI + logic in a single PHP file
- 📱 mobile responsive 
- 🧠 Supports any LLM API (OpenRouter, Together AI, etc.)
- ✍️ Customize system prompt and model behavior
- ⚡ No installation — simply copy-paste to WordPress
- 🧩 Minimal dependencies, fast setup

---

## 🚀 How to Use

1. **Open the PHP file** in VS Code or any text editor.
2. Add your API credentials:
   ```php
   $api_key = 'your-api-key';
   $model = 'your-model-name';

  3. Add URL endpont for your API Provider:
    ```php

    $response = wp_remote_post('https:// your-endpoint-here'

  4. Customize the system prompt inside the file to guide the assistant’s behavior.
  5. Copy the entire PHP code into your WordPress site using a plugin like:

✅ Code Snippets – WordPress Plugin

❗ Or embed it directly in your theme (functions.php) — not recommended for beginners

5. ✨ Save and activate — the chatbot will appear on your site! 🥳

--------------------------------------------------------------

🧩 Customization

🔧 System Prompt – Personalize the chatbot's role, tone, and intent

🔄 Model Switching – Swap LLMs by changing the $model variable

🎨 Style/UI – Modify inline HTML/CSS directly in the file

🛠 Requirements

- A WordPress website

- API key from a supported AI provider (e.g., OpenRouter, Together AI)

- The Code Snippets plugin (or manual theme editing)

📌 Notes

- Everything is contained in one PHP file — no JS or CSS dependencies

- Ideal for minimal deployments, demos, or fast LLM integrations into WordPress

- You can further extend with webhooks, logging, or chat history

📜 License

-This project is open-source under the MIT License.

🤝 Contributions

- Feel free to open issues, suggest features, or submit pull requests. Let’s build smarter web experiences together!


