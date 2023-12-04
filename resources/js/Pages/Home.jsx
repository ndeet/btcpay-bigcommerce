import React, { useState, useEffect } from 'react';
import Navigation from '@/Components/Navigation';
import Spinner from '@/Components/Spinner';
import { ApiService } from '@/Services';
import { Head, usePage } from '@inertiajs/react';
import SettingsForm from "@/Forms/SettingsForm";

function Home() {

    const [settings, setSettings] = useState({});
    const { btcpayData } = usePage().props;

    useEffect(() => {
        // Replace with your actual API calls
        ApiService.getSettings().then(response => {
            setSettings(response.data);
        });
    }, []);

    return (
        <>
            <Head title="BTCPay Server Settings" />

            {btcpayData && btcpayData.isAppContext ? (
                <>
                    <Navigation />

                    <div className="container mx-auto p-5">
                        <h2 className="text-2xl font-bold mb-5">BTCPay Server Settings</h2>
                        <SettingsForm initialData={settings} />
                    </div>

                </>
            ) : (
                <div className="flex items-center justify-center mt-8">
                    <h3 className="text-2xl font-bold">Access to this app is only allowed in a store context.</h3>
                </div>
            )}
        </>
    );

}

export default Home;
