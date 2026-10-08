import { useAppStore } from './useAppStore';

/**
 * Domain Composable: Authentication & User Account Management
 */
export function useAuth() {
  const store = useAppStore();
  return {
    currentUser: store.currentUser,
    setCurrentUser: store.setCurrentUser,
    isAuthenticated: store.isAuthenticated,
    login: store.login,
    register: store.register,
    resetPassword: store.resetPassword,
    findUserByIdentifier: store.findUserByIdentifier,
    logout: store.logout,
    addUser: store.addUser,
    updateUser: store.updateUser,
    reactivateUser: store.reactivateUser,
    deleteUser: store.deleteUser,
    adminResetUserPassword: store.adminResetUserPassword,
    updateUserPassword: store.updateUserPassword,
    createTemporaryOutsiderAccount: store.createTemporaryOutsiderAccount,
    evaluateOutsiderAccountExpirations: store.evaluateOutsiderAccountExpirations,
  };
}
