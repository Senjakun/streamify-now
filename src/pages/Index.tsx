import { useAuth } from "@/contexts/AuthContext";
import LandingPage from "@/components/home/LandingPage";
import DashboardPage from "@/components/home/DashboardPage";
const Index = () => { const { isAuthenticated } = useAuth(); return isAuthenticated ? <DashboardPage /> : <LandingPage />; };
export default Index;
