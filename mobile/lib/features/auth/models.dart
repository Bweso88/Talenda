/// Compte connecté et écoles où il a accès (GET /me).
class AppUser {
  const AppUser({required this.uuid, required this.name, required this.mustChangePassword, this.phone});

  final String uuid;
  final String name;
  final String? phone;
  final bool mustChangePassword;

  factory AppUser.fromJson(Map<String, dynamic> json) => AppUser(
    uuid: json['uuid'] as String,
    name: json['name'] as String,
    phone: json['phone'] as String?,
    mustChangePassword: json['must_change_password'] as bool? ?? false,
  );
}

class School {
  const School({required this.uuid, required this.name, required this.roles});

  final String uuid;
  final String name;
  final List<String> roles;

  factory School.fromMembershipJson(Map<String, dynamic> json) {
    final tenant = json['tenant'] as Map<String, dynamic>;
    return School(
      uuid: tenant['uuid'] as String,
      name: tenant['name'] as String,
      roles: [for (final role in json['roles'] as List? ?? const []) role.toString()],
    );
  }

  bool get isParent => roles.contains('parent');
}
