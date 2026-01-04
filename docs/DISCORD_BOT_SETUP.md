# Discord Bot Setup Guide

## Local Development Setup

### Prerequisites

1. **ngrok** (or similar tunneling service) - Required for local development
2. **Discord Developer Account** - Free at https://discord.com/developers/applications

### Step 1: Use Your Existing Discord Application (or Create New)

**If you already have a Discord application for OAuth:**
- You can use the same application for the bot! This is recommended.
- Your existing `DISCORD_CLIENT_ID` is the same as `DISCORD_APPLICATION_ID`

**If you need to create a new application:**
1. Go to https://discord.com/developers/applications
2. Click "New Application"
3. Give it a name (e.g., "Top Frag")
4. Click "Create"

### Step 2: Get Bot Credentials

1. In your application, go to the **General Information** tab
2. Copy the **Application ID** - this is your `DISCORD_APPLICATION_ID` (same as `DISCORD_CLIENT_ID` if using existing app)
3. Go to the **Bot** tab
4. Click "Add Bot" if you haven't already
5. Under **Token**, click "Reset Token" or "Copy" - this is your `DISCORD_BOT_TOKEN`
6. Under **Public Key**, copy the value - this is your `DISCORD_PUBLIC_KEY`

### Step 3: Configure Bot Permissions

1. Still in the **Bot** tab, scroll down to **Privileged Gateway Intents**
2. Enable **Server Members Intent** (required to get member information)
3. Go to **OAuth2** → **URL Generator**
4. Select scopes:
   - `bot`
   - `applications.commands` (for future slash commands)
5. Select bot permissions:
   - `Send Messages`
   - `Use Slash Commands`
6. Copy the generated URL at the bottom - use this to invite the bot to your server

### Step 4: Set Up ngrok

1. **Install ngrok**:
   ```bash
   # macOS
   brew install ngrok
   
   # Or download from https://ngrok.com/download
   ```

2. **Start your Laravel application**:
   ```bash
   cd web-app
   php artisan serve
   # Or use your start-dev script
   ```

3. **Start ngrok tunnel**:
   ```bash
   ngrok http 8000
   ```

4. **Copy the HTTPS URL** (e.g., `https://abc123.ngrok.io`)
   - This is your public webhook URL
   - Keep ngrok running while testing

### Step 5: Configure Discord Webhook URL

1. In Discord Developer Portal, go to your application
2. Go to **General Information** tab
3. Under **Interactions Endpoint URL**, enter:
   ```
   https://your-ngrok-url.ngrok.io/api/discord/webhook
   ```
4. Click "Save Changes"
5. Discord will send a PING request to verify the endpoint
   - If successful, you'll see a green checkmark
   - If it fails, check:
     - ngrok is running
     - Laravel server is running
     - URL is correct (must be HTTPS)
     - Signature verification is working

### Step 6: Configure Environment Variables

Add to your `web-app/.env` file:

```env
# Discord OAuth (for user authentication - existing)
DISCORD_CLIENT_ID=your_client_id_here
DISCORD_CLIENT_SECRET=your_client_secret_here
DISCORD_REDIRECT_URI=http://localhost:8000/api/auth/discord/callback

# Discord Bot Configuration (add these)
DISCORD_BOT_TOKEN=your_bot_token_here
DISCORD_PUBLIC_KEY=your_public_key_here
DISCORD_APPLICATION_ID=your_application_id_here  # Same as DISCORD_CLIENT_ID if using same app!
```

**Note:** If you're using the same Discord application for both OAuth and Bot:
- `DISCORD_APPLICATION_ID` = `DISCORD_CLIENT_ID` (they're the same value)

### Step 7: Register Slash Commands

**Important**: Discord slash commands must be registered with Discord's API **once per bot application** (not per server). After registration, the command will be available in all servers where your bot is added.

#### First-Time Setup Workflow

**For Production (Recommended)**:
```bash
cd web-app
php artisan discord:register-commands
```
- Register **once** after creating your bot
- Command available in ALL servers (takes up to 1 hour to propagate)
- Include this in your deployment script

**For Testing/Development**:
```bash
php artisan discord:register-commands --guild=YOUR_GUILD_ID
```
- Register for a specific test server
- Commands appear **immediately** (no waiting)
- Useful for local development

#### How It Works

1. **You register commands once** → Discord stores them for your bot application
2. **Bot is added to a server** → Commands automatically become available
3. **Users type `/setup`** → Command works immediately (if registered globally) or after guild registration

**You do NOT need to register commands:**
- Every time you add the bot to a new server
- Per-server (unless using guild-specific commands for testing)
- On every deployment (only when adding new commands)

1. **Register the `/setup` command**:
   ```bash
   cd web-app
   php artisan discord:register-commands
   ```
   
   This registers the command globally (available in all servers).
   
   **For faster testing (guild-specific, available immediately)**:
   ```bash
   php artisan discord:register-commands --guild=YOUR_GUILD_ID
   ```
   
   **How to get your Guild ID (Server ID)**:
   1. Open Discord (desktop app or web)
   2. Go to **User Settings** (gear icon next to your username)
   3. Go to **Advanced** section
   4. Enable **Developer Mode** (toggle it on)
   5. Close settings
   6. Right-click on your Discord server name (in the server list on the left)
   7. Click **Copy Server ID**
   8. Use that ID in the command: `php artisan discord:register-commands --guild=123456789012345678`
   
   **Alternative method** (if right-click doesn't work):
   - Right-click on any channel in your server
   - Click **Copy Channel ID** (you'll get a channel ID, but you can also get server ID this way)
   - Or use the server's context menu (three dots next to server name) → **Copy Server ID**

2. **Verify command registration**:
   - Global commands may take up to 1 hour to appear
   - Guild commands appear immediately
   - Type `/` in your Discord server to see available commands

### Step 8: Test the Setup

1. **Invite bot to your Discord server**:
   - Use the OAuth2 URL you generated in Step 3
   - Or create a direct invite: `https://discord.com/api/oauth2/authorize?client_id=YOUR_APPLICATION_ID&permissions=2048&scope=bot%20applications.commands`
   - **Important**: Include `applications.commands` scope for slash commands

2. **Run the `/setup` command**:
   - In your Discord server, type `/setup` and press Enter
   - The bot will process the command and link/create a clan
   - Check your Laravel logs: `storage/logs/laravel.log`
   - You should see: "Discord application command received" and "Setup command received"

3. **Verify the installer**:
   - The user running `/setup` must be a Top Frag user with a linked Discord account
   - Link your Discord account first: Go to Top Frag → Account Settings → Link Discord

4. **Check the result**:
   - If successful: Bot will respond with a success message (ephemeral, only visible to you)
   - If failed: Bot will respond with an error message explaining why
   - Check database: `clans` table should have a new row with `discord_guild_id`

### Troubleshooting

#### ngrok URL changes every time
- **Solution**: Use ngrok's free static domain or upgrade to a paid plan
- Or use a service like `localtunnel` or `serveo.net`

#### Signature verification fails
- **Check**: Public key is correct (no extra spaces, correct format)
- **Check**: Middleware is applied to the route
- **Check**: Request headers are being passed through correctly

#### Bot installation not detected
- **Check**: Interaction endpoint URL is set correctly in Discord
- **Check**: ngrok is forwarding to the correct port (8000)
- **Check**: Laravel logs for incoming requests
- **Note**: Bot installation is detected when an interaction happens in a guild context

#### "User must be a Top Frag member" error
- **Solution**: Create a user account in Top Frag
- Link your Discord account in Account Settings
- Make sure `discord_id` matches your Discord user ID

### Production Setup

For production, you don't need ngrok. Instead:

1. Deploy your Laravel application to a server with a public URL
2. Update the **Interactions Endpoint URL** in Discord Developer Portal:
   ```
   https://yourdomain.com/api/discord/webhook
   ```
3. Ensure your server has HTTPS (Discord requires HTTPS)
4. Update environment variables on your production server

### Useful Commands

```bash
# View Laravel logs in real-time
tail -f web-app/storage/logs/laravel.log

# Test webhook endpoint manually (will fail signature, but tests route)
curl -X POST http://localhost:8000/api/discord/webhook \
  -H "Content-Type: application/json" \
  -d '{"type": 1}'

# Check if route is registered
php artisan route:list --path=discord
```

### Next Steps

- The `/setup` command is now available!
- Add more bot functionality (e.g., `/clan-info`, `/clan-stats`)
- Set up monitoring and error tracking

