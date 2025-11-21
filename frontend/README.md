# FOS-Streaming v70 - Vue 3 Frontend

Modern, reactive frontend built with Vue 3, Vite 7, and Tailwind CSS.

## Tech Stack

- **Vue 3** - Progressive JavaScript Framework (Composition API)
- **Vite 7** - Next Generation Frontend Tooling
- **Vue Router 4** - Official routing library
- **Pinia** - Intuitive state management
- **Tailwind CSS 4** - Utility-first CSS framework
- **Axios** - Promise-based HTTP client
- **VueUse** - Collection of essential Vue composition utilities

## Project Structure

```
frontend/
├── src/
│   ├── assets/          # Static assets
│   ├── components/      # Reusable Vue components
│   ├── layouts/         # Layout components
│   │   └── MainLayout.vue
│   ├── router/          # Vue Router configuration
│   │   └── index.js
│   ├── stores/          # Pinia stores
│   │   └── main.js
│   ├── views/           # Page components
│   │   ├── DashboardView.vue
│   │   ├── LoginView.vue
│   │   ├── StreamsView.vue
│   │   ├── UsersView.vue
│   │   └── SettingsView.vue
│   ├── App.vue          # Root component
│   ├── main.js          # Application entry point
│   └── style.css        # Global styles + Tailwind
├── index.html           # HTML entry point
├── vite.config.js       # Vite configuration
└── package.json         # Dependencies
```

## Development

### Prerequisites

- Node.js 18+ and npm
- PHP 8.4+ (for backend)
- Running FOS-Streaming PHP backend

### Install Dependencies

```bash
cd frontend
npm install
```

### Run Development Server

```bash
npm run dev
```

The app will be available at `http://localhost:5173`

**API Proxy**: The dev server proxies `/api/*` requests to `http://localhost:8888`

### Build for Production

```bash
npm run build
```

Builds are output to `../public/` directory.

## Features

### Current Implementation

✅ **Authentication**
- Login/logout functionality
- Session management
- Protected routes

✅ **Dashboard**
- Real-time statistics (streams, system resources)
- Auto-refresh every 5 seconds
- Responsive design

✅ **Layout**
- Sidebar navigation
- Top navigation bar
- Consistent styling across pages

✅ **State Management**
- Pinia store for global state
- Reactive data updates

### API Integration

The frontend communicates with the PHP backend via REST API:

**Endpoints:**
- `POST /api/login` - User authentication
- `GET /api/dashboard` - Dashboard statistics
- `GET /api/streams` - Streams list

**Authentication:**
- Token-based auth stored in localStorage
- Automatic token injection in requests
- 401 handling with redirect to login

### Styling

Uses Tailwind CSS 4 with custom theme:

```css
--primary-color: #2c3e50
--success-color: #26B99A
--info-color: #3498DB
--warning-color: #F39C12
--danger-color: #E74C3C
```

## Deployment

### Development Mode

1. Start PHP backend: `php -S localhost:8888`
2. Start Vite dev server: `npm run dev`
3. Access frontend at: `http://localhost:5173`

### Production Mode

1. Build frontend: `npm run build`
2. Configure PHP server to serve from `public/` directory
3. Ensure `api.php` is accessible at `/api` endpoint

## Next Steps

- [ ] Implement Streams management (CRUD operations)
- [ ] Implement Users management
- [ ] Implement Settings page
- [ ] Add real-time WebSocket updates
- [ ] Add data tables with search/filter
- [ ] Add toast notifications
- [ ] Add loading states
- [ ] Add error boundaries
- [ ] Write unit tests (Vitest)
- [ ] Write E2E tests (Playwright)

## Contributing

When adding new features:

1. Create components in `src/components/`
2. Add views in `src/views/`
3. Update routes in `src/router/index.js`
4. Add API endpoints in `../api.php`
5. Update store in `src/stores/main.js` if needed

## License

All Rights Reserved - FOS-Streaming
