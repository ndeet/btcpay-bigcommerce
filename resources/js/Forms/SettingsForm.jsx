import React, { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import InputError from "@/Components/InputError";
import PrimaryButton from "@/Components/PrimaryButton";

export default function SettingsForm({ initialData }) {
    const { data, setData, post, put, processing, errors } = useForm({
        id: '',
        url: '',
        store_id: '',
        api_key: ''
    });

    useEffect(() => {
        if (initialData) {
            setData(prevData => ({
                ...prevData,
                ...initialData
            }));
        }
    }, [initialData]);

    const handleSuccess = () => {
        window.location.reload(); // Consider using Inertia's visit or reload methods for better SPA experience
    };

    const submit = (e) => {
        e.preventDefault();
        const action = initialData.id ? put : post;
        const routeName = initialData.id ? 'settings.update' : 'settings.store';

        action(route(routeName, initialData.id), { onSuccess: handleSuccess });
    };

    const renderInputField = (name, placeholder) => (
        <>
            <label
                htmlFor={name}
                className="block text-sm font-medium text-gray-700"
            >
                {placeholder}
            </label>
            <input
                type="text"
                value={data[name]}
                placeholder={placeholder}
                className="block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm"
                onChange={e => setData(name, e.target.value)}
            />
            <InputError message={errors[name]} className="mt-2" />
        </>
    );

    return (
        <div>
            <form onSubmit={submit}>
                {renderInputField('url', 'BTCPay Server URL')}
                {renderInputField('api_key', 'API Key')}
                {renderInputField('store_id', 'Store ID')}
                <PrimaryButton className="mt-8" disabled={processing}>Submit</PrimaryButton>
            </form>
        </div>
    );
}
