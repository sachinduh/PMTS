const roleRoutes = {
  director: '/director-dashboard',
  accountant: '/accountant-dashboard',
  procurement_officer: '/officer-dashboard',
  bec_member: '/bec-dashboard',
  specification_committee: '/specification-dashboard',
  it_admin: '/it-admin-dashboard',
  pending: '/pending',
};

export const getDefaultRoute = (role) => {
  return roleRoutes[role] || '/login';
};

export default roleRoutes;
