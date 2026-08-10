import { useEffect, useState } from 'react'
import { fetchCurrentUser, logout } from './api/auth'
import LoginForm from './components/LoginForm'
import Tracker from './components/Tracker'

// undefined = still checking; null = logged out; object = logged in.
// Split this way (rather than a boolean + separate user state) so there's
// no flash of the login form while the initial /api/user check is in flight.
function App() {
  const [currentUser, setCurrentUser] = useState(undefined)

  useEffect(() => {
    fetchCurrentUser().then(setCurrentUser)
  }, [])

  function handleLogout() {
    logout().then(() => setCurrentUser(null))
  }

  if (currentUser === undefined) {
    return null // brief; avoids a login-form flash for an already-authenticated session
  }

  if (currentUser === null) {
    return <LoginForm onLoggedIn={setCurrentUser} />
  }

  return <Tracker currentUser={currentUser} onLogout={handleLogout} />
}

export default App
