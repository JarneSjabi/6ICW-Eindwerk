class User:
    def __init__(self, user_id=None, firstname=None, lastname=None,
                 password_hash=None, created_at=None, updated_at=None):
        self.user_id = user_id
        self.firstname = firstname
        self.lastname = lastname
        self.password_hash = password_hash
        self.created_at = created_at
        self.updated_at = updated_at

    def to_dict(self):
        return {
            'id': self.user_id,
            'firstname': self.firstname,
            'lastname': self.lastname,
            'created_at': self.created_at.isoformat() if self.created_at else None,
            'updated_at': self.updated_at.isoformat() if self.updated_at else None
        }

    @classmethod
    def from_dict(cls, data):
        return cls(
            user_id=data.get('id'),
            firstname=data.get('firstname'),
            lastname=data.get('lastname'),
            password_hash=data.get('password_hash'),
            created_at=data.get('created_at'),
            updated_at=data.get('updated_at')
        )
