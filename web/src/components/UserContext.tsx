"use client";
import { createContext, useContext, useEffect, useState } from "react";
import { apiGet, type User } from "@/lib/api";

interface UserContextValue {
  user: User | null;
  loading: boolean;
}

const UserContext = createContext<UserContextValue>({ user: null, loading: true });

export function UserProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const load = () => {
      apiGet<User>("/user")
        .then(setUser)
        .catch((err) => console.error("Failed to fetch user:", err))
        .finally(() => setLoading(false));
    };
    load();
    window.addEventListener("profile-updated", load);
    return () => window.removeEventListener("profile-updated", load);
  }, []);

  return <UserContext.Provider value={{ user, loading }}>{children}</UserContext.Provider>;
}

export function useUser() {
  return useContext(UserContext);
}