import React, {useEffect} from 'react';
import { useForm } from '@inertiajs/react';
import InputError from "@/Components/InputError";
import PrimaryButton from "@/Components/PrimaryButton";


export default function SettingsForm({initialData}) {

    const { data, setData, post, put, get, processing, reset, errors } = useForm({
        id: '',
        url: '',
        store_id: '',
        api_key: ''
    });

    // useEffect to update form data when initialData changes
    useEffect(() => {
        if (initialData && (initialData.id || initialData.url || initialData.store_id || initialData.api_key)) {
            setData({
                ...data,
                id: initialData.id,
                url: initialData.url,
                store_id: initialData.store_id,
                api_key: initialData.api_key
            });
        }
    }, [initialData]);

    const handleSuccess = (response) => {
        // todo: fix this ugly hack
        window.location.reload();
    };

    const submit = (e) => {
        e.preventDefault();
        if (initialData.id) {
            put(route('settings.update', initialData.id), {onSuccess: handleSuccess});
        } else {
            post(route('settings.store'), {onSuccess: handleSuccess});
        }
    };

    const handleGetScriptsResponse = (response) => {
        console.warn('setting scripts response:');
        console.warn(response.data);
    }

    const installScript = (e) => {
        get(route('install-script'), {onSuccess: handleGetScriptsResponse});
    }

    return (
        <div>
            <form onSubmit={submit}>
            <input type="text"
                   value={data.url}
                   placeholder={'BTCPay Server URL'}
                   className="block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm"
                   onChange={e => setData('url', e.target.value)}
            ></input>
            <InputError message={errors.url} className="mt-2"/>

            <input type="text"
                   value={data.api_key}
                   placeholder={'API Key'}
                   className="block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm"
                   onChange={e => setData('api_key', e.target.value)}
            ></input>
            <InputError message={errors.api_key} className="mt-2"/>

            <input type="text"
                   value={data.store_id}
                   placeholder={'Store ID'}
                   className="block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm"
                   onChange={e => setData('store_id', e.target.value)}
            ></input>
            <InputError message={errors.store_id} className="mt-2"/>

            <PrimaryButton className="mt-8" disabled={processing}>{'Submit'}</PrimaryButton>
        </form>

            <PrimaryButton className="mt-8" onClick={installScript}>{'Set script'}</PrimaryButton>
        </div>
    );
}
