<!-- modules/yourmodulename/views/templates/front/chatbot.tpl -->

<div id="chatbot-container" style="position: fixed; bottom: 20px; right: 20px; width: 300px; height: 400px; z-index: 1000; background-color: white; border: 1px solid #ddd; border-radius: 10px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);">
    <div id="chatbot-header" style="padding: 10px; background-color: #007bff; color: white; border-top-left-radius: 10px; border-top-right-radius: 10px;">
        <span>Chat with us!</span>
    </div>
    <div id="chatbot-body" style="padding: 10px; height: 340px; overflow-y: auto;">
        <!-- Chat content goes here -->
    </div>
    <div id="chatbot-input" style="padding: 10px;">
        <input type="text" style="width: 100%;" placeholder="Type your message...">
    </div>
</div>
