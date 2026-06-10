import mysql.connector
from mysql.connector import Error
from mysql.connector import pooling
import os
from typing import Optional


class Database:
    def __init__(self):
        self.pool = None
        self.pool_name = os.getenv("DB_POOL_NAME", "backend_pool")
        self.pool_size = int(os.getenv("DB_POOL_SIZE", "8"))
        self.config = {
            "host": os.getenv("DB_HOST", "localhost"),
            "database": os.getenv("DB_NAME", "eindwerk"),
            "user": os.getenv("DB_USER", "root"),
            "password": os.getenv("DB_PASSWORD", ""),
            "charset": "utf8",
            "collation": "utf8_general_ci",
            "use_pure": True,
        }

    def _ensure_pool(self) -> bool:
        if self.pool is not None:
            return True
        return self.connect()

    def connect(self) -> bool:
        try:
            self.pool = pooling.MySQLConnectionPool(
                pool_name=self.pool_name,
                pool_size=self.pool_size,
                pool_reset_session=True,
                **self.config,
            )
            return True
        except Error as e:
            print(f"Error connecting to MySQL pool: {e}")
            self.pool = None
            return False
        except Exception as e:
            print(f"Unexpected error connecting to MySQL pool: {e}")
            self.pool = None
            return False

    def disconnect(self):
        """Drop the current pool reference."""
        self.pool = None

    def ensure_live(self) -> bool:
        return self._ensure_pool()

    def get_connection(self):
        if not self._ensure_pool():
            return None
        try:
            conn = self.pool.get_connection()
            if not conn.is_connected():
                conn.reconnect(attempts=1, delay=0)
            return conn
        except Error as e:
            print(f"Error acquiring MySQL connection from pool: {e}")
            return None
        except Exception as e:
            print(f"Unexpected error acquiring MySQL connection from pool: {e}")
            return None

    def execute_query(self, query: str, params: tuple = None) -> Optional[list]:
        conn = None
        cursor = None
        try:
            conn = self.get_connection()
            if conn is None:
                return None

            cursor = conn.cursor(dictionary=True)
            cursor.execute(query, params or ())
            return cursor.fetchall()
        except ReferenceError as e:
            print(f"ReferenceError in execute_query, attempting reconnect: {e}")
            try:
                if cursor:
                    cursor.close()
                if conn:
                    conn.close()
                conn = self.get_connection()
                if conn is None:
                    return None
                cursor = conn.cursor(dictionary=True)
                cursor.execute(query, params or ())
                return cursor.fetchall()
            except Exception as e2:
                print(f"Error executing query after reconnect: {e2}")
                return None
        except Error as e:
            print(f"Error executing query: {e}")
            return None
        except Exception as e:
            print(f"Unexpected error executing query: {e}")
            return None
        finally:
            try:
                if cursor:
                    cursor.close()
            except Exception:
                pass
            try:
                if conn:
                    conn.close()
            except Exception:
                pass

    def execute_update(self, query: str, params: tuple = None) -> Optional[int]:
        conn = None
        cursor = None
        try:
            conn = self.get_connection()
            if conn is None:
                return None

            cursor = conn.cursor()
            cursor.execute(query, params or ())
            conn.commit()
            affected_rows = cursor.rowcount
            last_id = cursor.lastrowid
            return last_id if last_id else affected_rows
        except ReferenceError as e:
            print(f"ReferenceError in execute_update, attempting reconnect: {e}")
            try:
                if cursor:
                    cursor.close()
                if conn:
                    conn.close()
                conn = self.get_connection()
                if conn is None:
                    return None
                cursor = conn.cursor()
                cursor.execute(query, params or ())
                conn.commit()
                affected_rows = cursor.rowcount
                last_id = cursor.lastrowid
                return last_id if last_id else affected_rows
            except Exception as e2:
                print(f"Error executing update after reconnect: {e2}")
                try:
                    if conn:
                        conn.rollback()
                except Exception:
                    pass
                return None
        except Error as e:
            print(f"Error executing update: {e}")
            try:
                if conn:
                    conn.rollback()
            except Exception:
                pass
            return None
        except Exception as e:
            print(f"Unexpected error executing update: {e}")
            try:
                if conn:
                    conn.rollback()
            except Exception:
                pass
            return None
        finally:
            try:
                if cursor:
                    cursor.close()
            except Exception:
                pass
            try:
                if conn:
                    conn.close()
            except Exception:
                pass
